'use strict';

// Native <dialog> elements ignore clicks on their backdrop; close the dialog when both the press and the
// release happen outside its box, so dragging a text selection out of a field never closes it.
let pressedOutside = false;

const outside = (dialog, event) => {
    const box = dialog.getBoundingClientRect();
    return event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom;
};

document.addEventListener('mousedown', (event) => {
    pressedOutside = event.target instanceof HTMLDialogElement && event.target.open && outside(event.target, event);
});

document.addEventListener('click', (event) => {
    const dialog = event.target;
    if (pressedOutside && dialog instanceof HTMLDialogElement && dialog.open && outside(dialog, event)) dialog.close();
    pressedOutside = false;
});
