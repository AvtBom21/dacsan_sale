(function () {
    const shell = document.querySelector('.admin-shell');
    if (!shell) {
        return;
    }

    window.DSND_ADMIN = {
        csrfToken: shell.getAttribute('data-csrf') || '',
    };
})();
