/* Editor Component (Quill) */

export function initEditor() {
  const editorElement = document.getElementById("quill-editor");
  console.log("Initializing Editor. Element found:", !!editorElement);

  if (editorElement) {
    if (typeof Quill !== "undefined") {
      console.log("Quill is defined. Creating instance...");
      try {
        new Quill(editorElement, {
          theme: "snow",
          placeholder: "Type your content here...",
          modules: {
            toolbar: [
              [{ header: [1, 2, 3, false] }],
              ["bold", "italic", "underline", "strike"],
              [{ list: "ordered" }, { list: "bullet" }],
              [{ direction: "rtl" }],
              [{ color: [] }, { background: [] }],
              [{ align: [] }],
              ["link", "image", "code-block", "clean"],
            ],
          },
        });
        console.log("Quill instance created.");
      } catch (error) {
        console.error("Error creating Quill instance:", error);
      }
    } else {
      console.warn("Quill library not found. Make sure to include the CDN.");
    }
  }
}

// Initialize Editor when DOM is ready
// Initialize Editor when DOM is ready or immediately if already ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () => {
    initEditor();
  });
} else {
  initEditor();
}
