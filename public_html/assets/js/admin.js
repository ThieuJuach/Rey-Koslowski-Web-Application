/**
 * Rey Koslowski Academic Website - Admin Dashboard JavaScript
 * Handles drag-and-drop reordering, inline editing, and file uploads.
 */

/*
 * Base URL for API calls.
 *
 * This used to read `typeof SITE_URL !== 'undefined' ? SITE_URL : ''`, but SITE_URL
 * was never defined anywhere in JS, so it silently evaluated to '' on every page.
 * Deriving it from this script's own <script src> is always correct, whatever
 * subdirectory the site is installed in. document.currentScript is only readable
 * while the script is executing, so it is captured here at the top level.
 */
const siteUrl = (function () {
    const src = document.currentScript && document.currentScript.src;
    return src ? src.replace(/\/assets\/js\/admin\.js(\?.*)?$/, '') : '';
})();

/*
 * CSRF token for fetch()-based requests.
 *
 * Every admin page already renders at least one <?= csrfField() ?>, so we reuse that
 * hidden input rather than adding a meta tag to fifteen separate page heads.
 *
 * This was the bug behind drag-and-drop reordering silently doing nothing:
 * api/reorder-sections.php requires a CSRF token, admin.js never sent one, so every
 * reorder came back 403 and the error was only ever written to the console.
 */
function csrfToken() {
    const input = document.querySelector('input[name="csrf_token"]');
    return input ? input.value : '';
}

/**
 * Turn a fetch Response into a useful message, treating 429 as a first-class case
 * rather than a generic failure.
 */
async function readApiError(response) {
    if (response.status === 429) {
        const retry = response.headers.get('Retry-After');
        return retry
            ? `Too many requests — try again in ${retry}s`
            : 'Too many requests — please slow down';
    }
    if (response.status === 401) return 'Session expired — please log in again';
    if (response.status === 403) return 'Security token expired — reload the page';
    try {
        const body = await response.json();
        return body.error || `Request failed (${response.status})`;
    } catch (e) {
        return `Request failed (${response.status})`;
    }
}

/** Small transient banner so failures are visible instead of console-only. */
function showToast(message, isError) {
    let toast = document.getElementById('adminToast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'adminToast';
        toast.className = 'admin-toast';
        document.body.appendChild(toast);
    }
    toast.textContent = message;
    toast.classList.toggle('admin-toast--error', !!isError);
    toast.classList.add('admin-toast--visible');
    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(() => {
        toast.classList.remove('admin-toast--visible');
    }, 3500);
}

document.addEventListener('DOMContentLoaded', () => {

    // --- File Upload with Drag & Drop ---
    const uploadZones = document.querySelectorAll('.upload-zone');
    uploadZones.forEach(zone => {
        const input = zone.querySelector('input[type="file"]');

        zone.addEventListener('click', () => input?.click());

        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('upload-zone--dragover');
        });

        zone.addEventListener('dragleave', () => {
            zone.classList.remove('upload-zone--dragover');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('upload-zone--dragover');
            if (input && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                input.dispatchEvent(new Event('change'));
            }
        });

        if (input) {
            input.addEventListener('change', () => {
                handleFileUpload(input, zone);
            });
        }
    });

    async function handleFileUpload(input, zone) {
        const file = input.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', zone.dataset.uploadType || 'image');
        formData.append('csrf_token', csrfToken());

        const label = zone.querySelector('.upload-zone__text');
        const originalText = label?.textContent;
        if (label) label.textContent = 'Uploading...';

        try {
            const response = await fetch(siteUrl + '/api/upload-image.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            if (!response.ok) {
                const message = await readApiError(response);
                if (label) label.textContent = 'Upload failed';
                showToast(message, true);
                setTimeout(() => { if (label) label.textContent = originalText; }, 2500);
                return;
            }

            const result = await response.json();

            if (result.success) {
                if (label) label.textContent = 'Uploaded successfully!';
                // Update preview if available
                const preview = zone.closest('.admin-card')?.querySelector('.upload-preview');
                if (preview) {
                    const img = document.createElement('img');
                    img.src = siteUrl + '/' + result.path;
                    img.style.maxWidth = '100%';
                    img.style.borderRadius = '0.375rem';
                    preview.replaceChildren(img);
                }
                setTimeout(() => {
                    if (label) label.textContent = originalText;
                }, 2000);
            } else {
                if (label) label.textContent = 'Upload failed';
                showToast(result.error || 'Unknown error', true);
            }
        } catch (err) {
            if (label) label.textContent = 'Upload failed. Check connection.';
            showToast('Upload failed — check your connection', true);
        }
    }

    // --- Drag & Drop Reordering ---
    const sortableLists = document.querySelectorAll('[data-sortable]');
    sortableLists.forEach(list => {
        let dragItem = null;

        list.querySelectorAll('[data-sortable-item]').forEach(item => {
            item.setAttribute('draggable', 'true');

            item.addEventListener('dragstart', () => {
                dragItem = item;
                item.style.opacity = '0.5';
            });

            item.addEventListener('dragend', () => {
                item.style.opacity = '1';
                dragItem = null;
                saveSortOrder(list);
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (!dragItem || dragItem === item) return;
                const rect = item.getBoundingClientRect();
                const midY = rect.top + rect.height / 2;
                if (e.clientY < midY) {
                    item.parentNode.insertBefore(dragItem, item);
                } else {
                    item.parentNode.insertBefore(dragItem, item.nextSibling);
                }
            });
        });
    });

    async function saveSortOrder(list) {
        const items = list.querySelectorAll('[data-sortable-item]');
        const order = Array.from(items).map((item, index) => ({
            id: item.dataset.id,
            order: index
        }));

        try {
            const response = await fetch(siteUrl + '/api/reorder-sections.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    // Sent as a header as well as in the body so either check passes.
                    'X-CSRF-Token': csrfToken()
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    table: list.dataset.sortable,
                    order: order,
                    csrf_token: csrfToken()
                })
            });

            if (!response.ok) {
                showToast(await readApiError(response), true);
                return;
            }

            const result = await response.json();
            if (result.success) {
                showToast('Order saved', false);
            } else {
                showToast(result.error || 'Could not save order', true);
            }
        } catch (err) {
            showToast('Could not save order — check your connection', true);
        }
    }

    // --- Inline Content Saving ---
    const saveButtons = document.querySelectorAll('[data-save]');
    saveButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const form = btn.closest('form');
            if (!form) return;

            const formData = new FormData(form);
            // Guarantee a token even if this particular form omits csrfField().
            if (!formData.get('csrf_token')) {
                formData.append('csrf_token', csrfToken());
            }

            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const response = await fetch(siteUrl + '/api/save-content.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    btn.textContent = 'Error saving';
                    btn.classList.add('btn--danger');
                    showToast(await readApiError(response), true);
                } else {
                    const result = await response.json();
                    if (result.success) {
                        btn.textContent = 'Saved!';
                        btn.classList.add('btn--accent');
                    } else {
                        btn.textContent = 'Error saving';
                        btn.classList.add('btn--danger');
                        showToast(result.error || 'Could not save', true);
                    }
                }
            } catch (err) {
                btn.textContent = 'Error saving';
                btn.classList.add('btn--danger');
                showToast('Could not save — check your connection', true);
            }

            setTimeout(() => {
                btn.disabled = false;
                btn.textContent = 'Save Changes';
                btn.classList.remove('btn--accent', 'btn--danger');
            }, 2000);
        });
    });
});
