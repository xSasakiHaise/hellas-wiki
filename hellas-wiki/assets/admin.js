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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
