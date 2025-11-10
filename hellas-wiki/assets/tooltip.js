(function(){
const tooltip = document.createElement('div');
tooltip.className = 'hellaswiki-tooltip';
document.body.appendChild(tooltip);

let activeLink = null;

document.addEventListener('mouseover', async (event) => {
const link = event.target.closest('a[data-hellaswiki]');
if (!link) {
tooltip.style.display = 'none';
return;
}

activeLink = link;
const slug = link.dataset.slug || link.dataset.hellaswiki;
const postType = link.dataset.postType;
const id = link.dataset.id;

try {
const url = new URL(hellasWikiTooltip.endpoint);
if (id) {
    url.searchParams.set('id', id);
}
if (postType) {
    url.searchParams.set('type', postType.replace(/^wiki_/, ''));
}
if (!id && slug) {
    url.searchParams.set('slug', slug);
}

const response = await fetch(url.toString());
const data = await response.json();

if (!data || !data.title) {
return;
}

tooltip.innerHTML = `<strong>${data.title}</strong>`;
if (data.fields) {
tooltip.innerHTML += '<ul>';
Object.entries(data.fields).forEach(([key, value]) => {
if (!value) {
return;
}
tooltip.innerHTML += `<li><span>${key.replace(/_/g, ' ')}:</span> ${value}</li>`;
});
tooltip.innerHTML += '</ul>';
}

const rect = link.getBoundingClientRect();
tooltip.style.display = 'block';
tooltip.style.top = `${window.scrollY + rect.top - tooltip.offsetHeight - 12}px`;
tooltip.style.left = `${window.scrollX + rect.left}px`;
} catch (err) {
console.error('Tooltip error', err);
}
});

document.addEventListener('mouseout', () => {
if (!activeLink) {
return;
}
tooltip.style.display = 'none';
activeLink = null;
});
})();
