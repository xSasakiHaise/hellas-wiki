(function(){
const queueTable = document.querySelector('.hellaswiki-queue');

if (queueTable) {
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
})();
