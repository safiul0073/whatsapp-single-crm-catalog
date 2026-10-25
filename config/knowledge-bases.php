<?php

return [
    /*
     * Create the "Store Help" knowledge base, "Store Assistant" chatbot and website
     * widget automatically for every new workspace.
     */
    'auto_provision' => (bool) env('KNOWLEDGE_BASE_AUTO_PROVISION', true),
];
