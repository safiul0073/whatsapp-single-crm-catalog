document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-password-toggle]").forEach((toggle) => {
        const wrap = toggle.closest(".relative");
        const input = wrap?.querySelector('input[type="password"], input[type="text"]');
        const eye = toggle.querySelector("[data-eye]");
        const eyeOff = toggle.querySelector("[data-eye-off]");

        if (!input) {
            return;
        }

        toggle.addEventListener("click", () => {
            const show = input.type === "password";
            input.type = show ? "text" : "password";
            toggle.setAttribute("aria-label", show ? "Hide password" : "Show password");
            eye?.classList.toggle("hidden", show);
            eyeOff?.classList.toggle("hidden", !show);
        });
    });
});
