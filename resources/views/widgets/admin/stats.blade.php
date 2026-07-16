<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
    <x-ui.kpi-card
        :title="__('Main Users')"
        :value="$stats['main_users']"
        icon="ph-users-three"
        color="primary"
    />
    <x-ui.kpi-card
        :title="__('Active Main Users')"
        :value="$stats['active_main_users']"
        icon="ph-user-check"
        color="success"
    />
    <x-ui.kpi-card
        :title="__('Total Users')"
        :value="$stats['total_users']"
        icon="ph-users"
        color="secondary"
    />
    <x-ui.kpi-card
        :title="__('Messages (30d)')"
        :value="$stats['messages_last_30_days']"
        icon="ph-chat-circle-text"
        color="primary"
    />
    <x-ui.kpi-card
        :title="__('Active Automations')"
        :value="$stats['active_automations']"
        icon="ph-flow-arrow"
        color="error"
    />
    <x-ui.kpi-card
        :title="__('Connected Channels')"
        :value="$stats['connected_channels']"
        icon="ph-plugs-connected"
        color="success"
    />
    <x-ui.kpi-card
        :title="__('Total Widgets')"
        :value="$stats['total_widgets']"
        icon="ph-puzzle-piece"
        color="info"
    />
    <x-ui.kpi-card
        :title="__('Total Templates')"
        :value="$stats['total_templates']"
        icon="ph-file-text"
        color="warning"
    />
    <x-ui.kpi-card
        :title="__('Total Contacts')"
        :value="$stats['total_contacts']"
        icon="ph-address-book"
        color="secondary"
    />
    <x-ui.kpi-card
        :title="__('Total Campaigns')"
        :value="$stats['total_campaigns']"
        icon="ph-megaphone"
        color="primary"
    />
</div>
