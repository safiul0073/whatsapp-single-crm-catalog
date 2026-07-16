/* Footer newsletter subscription.
   Driven by [data-newsletter] on the form and [data-newsletter-note]
   for the message target. */

document.addEventListener("DOMContentLoaded", () => {
  "use strict";

  const forms = document.querySelectorAll("[data-newsletter]");
  if (!forms.length) return;

  forms.forEach((form) => {
    if (form.dataset.newsletterBound === "true") return;
    form.dataset.newsletterBound = "true";

    form.addEventListener("submit", (e) => {
      "use strict";

      e.preventDefault();
      const input = form.querySelector('input[type="email"]');
      const note =
        form.querySelector("[data-newsletter-note]") ||
        form.parentElement?.querySelector("[data-newsletter-note]");
      if (!input || !input.value.trim()) return;
      const submit = form.querySelector('button[type="submit"]');
      const token = form.querySelector('input[name="_token"]')?.value ||
        document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
      const formData = new FormData(form);
      formData.set("email", input.value.trim());

      input.disabled = true;
      if (submit) submit.disabled = true;
      if (note) note.textContent = "Subscribing...";

      fetch(form.getAttribute("action") || "/newsletter/subscribe", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": token || "",
        },
        body: formData,
      })
        .then(async (response) => {
          const data = await response.json().catch(() => ({}));

          if (!response.ok) {
            const message = data.message ||
              data.errors?.email?.[0] ||
              "Please try again in a moment.";

            throw new Error(message);
          }

          return data;
        })
        .then((data) => {
          if (note) note.textContent = data.message || "Thank you for subscribing!";
          input.value = "";
        })
        .catch((error) => {
          if (note) note.textContent = error.message;
        })
        .finally(() => {
          input.disabled = false;
          if (submit) submit.disabled = false;
        });
    });
  });
});
