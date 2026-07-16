// Marketing auth links should always navigate, even if another script on the
// page accidentally prevents default link behavior.
document.addEventListener(
    "click",
    (event) => {
        const link = event.target.closest("[data-auth-nav-link]");

        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        window.location.assign(link.href);
    },
    { capture: true },
);
