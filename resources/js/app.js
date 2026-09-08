document.addEventListener('click', (event) => {
    const openTrigger = event.target.closest('[data-dialog-open]');
    const closeTrigger = event.target.closest('[data-dialog-close]');

    if (openTrigger) {
        document.getElementById(openTrigger.dataset.dialogOpen)?.showModal();
    }

    if (closeTrigger) {
        document.getElementById(closeTrigger.dataset.dialogClose)?.close();
    }
});

document.querySelectorAll('[data-dialog-auto-open]').forEach((dialog) => dialog.showModal());
