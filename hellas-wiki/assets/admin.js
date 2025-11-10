(function(){
    function initQueueActions() {
        const queueTable = document.querySelector('.hellaswiki-queue');

        if (!queueTable) {
            return;
        }

        queueTable.addEventListener('click', function(event){
            const target = event.target;

            if (!target.dataset.action) {
                return;
            }

            event.preventDefault();

            const key = target.dataset.key;
            const action = target.dataset.action;

            if (!key) {
                return;
            }

            const endpoint = action === 'process' ? 'queue/process' : 'queue/dismiss';

            fetch(hellasWikiAdmin.rest.root + endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': hellasWikiAdmin.rest.nonce
                },
                body: JSON.stringify({ key })
            }).then(response => response.json()).then(() => {
                target.closest('tr').remove();
            }).catch(() => {
                window.alert('Unable to update queue item.');
            });
        });
    }

    function initUpdateCard() {
        if (!window.wp || !wp.apiFetch) {
            return;
        }

        const container = document.querySelector('[data-hellaswiki-update]');

        if (!container) {
            return;
        }

        const currentEl = container.querySelector('[data-update-current]');
        const latestEl = container.querySelector('[data-update-latest]');
        const statusEl = container.querySelector('[data-update-status]');
        const repoEl = container.querySelector('[data-update-repo]');
        const descriptionEl = container.querySelector('[data-update-description]');
        const checkBtn = container.querySelector('[data-update-check]');
        const updateBtn = container.querySelector('[data-update-run]');
        const spinner = container.querySelector('[data-update-spinner]');

        let state = null;
        let busy = false;

        function setBusy(nextBusy) {
            busy = nextBusy;

            if (spinner) {
                spinner.style.visibility = busy ? 'visible' : 'hidden';
            }

            if (checkBtn) {
                checkBtn.disabled = busy;
            }

            if (updateBtn) {
                updateBtn.disabled = busy || !(state && state.has_update);
            }

            container.classList.toggle('is-busy', busy);
        }

        function render(data) {
            state = data || {};

            if (currentEl) {
                currentEl.textContent = data.current || '—';
            }

            if (latestEl) {
                const latestParts = [];

                if (data.latest) {
                    latestParts.push(data.latest);
                }

                if (data.source) {
                    latestParts.push(data.source);
                }

                latestEl.textContent = latestParts.length ? latestParts.join(' • ') : '—';
            }

            if (repoEl) {
                repoEl.textContent = data.repository || '—';
            }


            if (descriptionEl) {
                if (data.reason === 'no_releases' && data.releases_only) {
                    descriptionEl.textContent = wp.i18n ? wp.i18n.__('No published releases were found. Disable “Use only GitHub releases” to fall back to the main branch.', 'hellas-wiki') : 'No published releases were found. Disable “Use only GitHub releases” to fall back to the main branch.';
                } else {
                    descriptionEl.textContent = '';
                }
            }

            if (statusEl) {
                if (data.reason === 'no_releases' && data.releases_only) {
                    statusEl.textContent = wp.i18n ? wp.i18n.__('Update unavailable (no releases)', 'hellas-wiki') : 'Update unavailable (no releases)';
                } else if (data.has_update) {
                    statusEl.textContent = wp.i18n ? wp.i18n.__('Update available', 'hellas-wiki') : 'Update available';
                } else {
                    statusEl.textContent = wp.i18n ? wp.i18n.__('Up to date', 'hellas-wiki') : 'Up to date';
                }
            }

            container.classList.toggle('has-update', Boolean(data.has_update));

            if (updateBtn) {
                updateBtn.disabled = busy || !data.has_update;
            }
        }

        function showError(message) {
            if (statusEl) {
                statusEl.textContent = message;
            }

            window.alert(message);
        }

        function fetchStatus(force) {
            setBusy(true);

            const suffix = force ? '?force=1&ts=' + Date.now() : '?ts=' + Date.now();

            return wp.apiFetch({ path: '/hellaswiki/v1/update/check' + suffix }).then(data => {
                render(data);
                return data;
            }).catch(error => {
                const message = error && error.message ? error.message : 'Unable to check for updates.';
                showError(message);
            }).finally(() => {
                setBusy(false);
            });
        }

        function runUpdate() {
            setBusy(true);

            const nonce = hellasWikiAdmin && hellasWikiAdmin.update ? hellasWikiAdmin.update.nonce : '';
            const suffix = '?_=' + Date.now() + (nonce ? '&_wpnonce=' + encodeURIComponent(nonce) : '');

            return wp.apiFetch({
                path: '/hellaswiki/v1/update/run' + suffix,
                method: 'POST'
            }).then(response => {
                const hasI18n = !!(wp.i18n && wp.i18n.__);
                const sprintf = hasI18n && wp.i18n.sprintf ? wp.i18n.sprintf : null;
                const message = response && response.new
                    ? (sprintf ? sprintf(wp.i18n.__('Updated to %s', 'hellas-wiki'), response.new) : (hasI18n ? wp.i18n.__('Updated to %s', 'hellas-wiki').replace('%s', response.new) : 'Updated to ' + response.new))
                    : (hasI18n ? wp.i18n.__('Update completed.', 'hellas-wiki') : 'Update completed.');
                window.alert(message);
                return fetchStatus(true);
            }).catch(error => {
                const message = error && error.message ? error.message : 'Update failed.';
                showError(message);
            }).finally(() => {
                setBusy(false);
            });
        }

        if (checkBtn) {
            checkBtn.addEventListener('click', function(){
                fetchStatus(true);
            });
        }

        if (updateBtn) {
            updateBtn.addEventListener('click', function(){
                runUpdate();
            });
        }

        fetchStatus(false);
    }

    function init() {
        initQueueActions();
        initUpdateCard();
        initHealthCard();
        initParseCard();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

function initHealthCard() {
    if (!window.wp || !wp.apiFetch) {
        return;
    }

    const container = document.querySelector('[data-hellaswiki-health]');

    if (!container) {
        return;
    }

    const repoEl = container.querySelector('[data-health-repo]');
    const tokenEl = container.querySelector('[data-health-token]');
    const pollerEl = container.querySelector('[data-health-poller]');
    const lastPollEl = container.querySelector('[data-health-last-poll]');
    const lastWebhookEl = container.querySelector('[data-health-last-webhook]');
    const webhookStatusEl = container.querySelector('[data-health-webhook-status]');
    const queueEl = container.querySelector('[data-health-queue]');
    const nextCronEl = container.querySelector('[data-health-next-cron]');
    const warningEl = container.querySelector('[data-health-warning]');
    const pollBtn = container.querySelector('[data-health-poll]');
    const flushBtn = container.querySelector('[data-health-flush]');

    let busy = false;

    function setBusy(next) {
        busy = next;
        container.classList.toggle('is-busy', busy);
        if (pollBtn) {
            pollBtn.disabled = busy;
        }
        if (flushBtn) {
            flushBtn.disabled = busy;
        }
    }

    function render(data) {
        if (!data) {
            return;
        }

        if (repoEl) {
            repoEl.textContent = data.repo || '—';
        }
        if (tokenEl) {
            tokenEl.textContent = data.token_present ? '✔︎' : '✘';
        }
        if (pollerEl) {
            pollerEl.textContent = data.poller_enabled ? '✔︎' : '✘';
        }
        if (lastPollEl) {
            lastPollEl.textContent = data.last_poll_at ? `${data.last_poll_at} (${data.last_poll_result || '—'})` : '—';
        }
        if (lastWebhookEl) {
            lastWebhookEl.textContent = data.last_webhook_at || '—';
        }
        if (webhookStatusEl) {
            webhookStatusEl.textContent = data.last_webhook_status || '—';
        }
        if (queueEl) {
            queueEl.textContent = typeof data.queue_count !== 'undefined' ? data.queue_count : '0';
        }
        if (nextCronEl) {
            nextCronEl.textContent = data.next_cron ? data.next_cron : '—';
        }
        if (warningEl) {
            const warnings = [];
            if (data.repo && data.repo.toLowerCase().endsWith('/hellas-wiki')) {
                warnings.push('⚠️ Repository points to hellas-wiki. Switch to xSasakiHaise/hellasforms.');
            }
            if (data.cron_disabled) {
                warnings.push('⚠️ DISABLE_WP_CRON is enabled. Ensure a real cron job calls wp-cron.php.');
            }
            warningEl.textContent = warnings.join(' ');
        }
    }

    function fetchHealth() {
        setBusy(true);
        wp.apiFetch({ path: '/hellaswiki/v1/health?ts=' + Date.now() }).then(render).catch(() => {
            if (warningEl) {
                warningEl.textContent = 'Unable to fetch health data.';
            }
        }).finally(() => {
            setBusy(false);
        });
    }

    function runPoll() {
        setBusy(true);
        wp.apiFetch({
            path: '/hellaswiki/v1/poll',
            method: 'POST'
        }).then(() => fetchHealth()).catch(() => {
            window.alert('Poll failed. Check logs for details.');
        }).finally(() => {
            setBusy(false);
        });
    }

    function flushRewrites() {
        setBusy(true);
        wp.apiFetch({
            path: '/hellaswiki/v1/flush-rewrites',
            method: 'POST'
        }).then(() => {
            window.alert('Rewrite rules flushed.');
        }).catch(() => {
            window.alert('Failed to flush rewrite rules.');
        }).finally(() => {
            setBusy(false);
        });
    }

    if (pollBtn) {
        pollBtn.addEventListener('click', runPoll);
    }
    if (flushBtn) {
        flushBtn.addEventListener('click', flushRewrites);
    }

    fetchHealth();
}

function initParseCard() {
    if (!window.wp || !wp.apiFetch) {
        return;
    }

    const container = document.querySelector('[data-hellaswiki-parse]');

    if (!container) {
        return;
    }

    const urlInput = container.querySelector('[data-parse-url]');
    const payloadInput = container.querySelector('[data-parse-payload]');
    const runBtn = container.querySelector('[data-parse-run]');
    const spinner = container.querySelector('[data-parse-spinner]');
    const output = container.querySelector('[data-parse-output]');

    let busy = false;

    function setBusy(next) {
        busy = next;
        container.classList.toggle('is-busy', busy);
        if (spinner) {
            spinner.style.visibility = busy ? 'visible' : 'hidden';
        }
        if (runBtn) {
            runBtn.disabled = busy;
        }
    }

    function runParse() {
        if (!runBtn) {
            return;
        }

        const url = urlInput ? urlInput.value.trim() : '';
        const payload = payloadInput ? payloadInput.value.trim() : '';

        if (!url && !payload) {
            window.alert('Provide a JSON payload or URL to test.');
            return;
        }

        const data = {};
        if (url) {
            data.url = url;
        }
        if (payload) {
            data.payload = payload;
        }

        setBusy(true);

        wp.apiFetch({
            path: '/hellaswiki/v1/import/parse',
            method: 'POST',
            data
        }).then(response => {
            if (output) {
                output.textContent = JSON.stringify(response, null, 2);
            }
        }).catch(error => {
            const message = error && error.message ? error.message : 'Parse failed.';
            if (output) {
                output.textContent = message;
            }
            window.alert(message);
        }).finally(() => {
            setBusy(false);
        });
    }

    if (runBtn) {
        runBtn.addEventListener('click', runParse);
    }
}
