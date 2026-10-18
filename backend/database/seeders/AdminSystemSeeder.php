<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class AdminSystemSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'label' => 'Super Admin', 'description' => 'Full system access'],
            ['name' => 'admin', 'label' => 'Admin', 'description' => 'Administrative access'],
            ['name' => 'moderator', 'label' => 'Moderator', 'description' => 'User and content moderation'],
            ['name' => 'support', 'label' => 'Support', 'description' => 'View users and support'],
        ];

        foreach ($roles as $role) {
            AdminRole::firstOrCreate(['name' => $role['name']], $role);
        }

        $permissions = [
            ['name' => 'dashboard.view', 'label' => 'View Dashboard', 'group' => 'dashboard'],
            ['name' => 'users.view', 'label' => 'View Users', 'group' => 'users'],
            ['name' => 'users.manage', 'label' => 'Manage Users', 'group' => 'users'],
            ['name' => 'messages.view', 'label' => 'View Messages', 'group' => 'messages'],
            ['name' => 'messages.manage', 'label' => 'Manage Messages', 'group' => 'messages'],
            ['name' => 'subscriptions.view', 'label' => 'View Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'subscriptions.manage', 'label' => 'Manage Subscriptions', 'group' => 'subscriptions'],
            ['name' => 'payments.view', 'label' => 'View Payments', 'group' => 'payments'],
            ['name' => 'payments.refund', 'label' => 'Refund Payments', 'group' => 'payments'],
            ['name' => 'security.view', 'label' => 'View Security Center', 'group' => 'security'],
            ['name' => 'security.manage', 'label' => 'Manage Security', 'group' => 'security'],
            ['name' => 'settings.view', 'label' => 'View Settings', 'group' => 'settings'],
            ['name' => 'settings.manage', 'label' => 'Manage Settings', 'group' => 'settings'],
            ['name' => 'notifications.send', 'label' => 'Send Notifications', 'group' => 'notifications'],
        ];

        foreach ($permissions as $permission) {
            AdminPermission::firstOrCreate(['name' => $permission['name']], $permission);
        }

        $superAdmin = AdminRole::where('name', 'super_admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->sync(AdminPermission::pluck('id'));
        }

        $settings = [
            ['key' => 'app.name', 'value' => 'Wavixa', 'type' => 'string', 'group' => 'general', 'label' => 'Platform Name'],
            ['key' => 'app.logo', 'value' => '/logo.png', 'type' => 'string', 'group' => 'general', 'label' => 'Logo URL'],
            ['key' => 'app.support_email', 'value' => 'support@wavixa.net', 'type' => 'string', 'group' => 'general', 'label' => 'Support Email'],
            ['key' => 'paypal.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'payments', 'label' => 'PayPal Enabled'],
            ['key' => 'paypal.client_id', 'value' => '', 'type' => 'string', 'group' => 'payments', 'label' => 'PayPal Client ID'],
            ['key' => 'paypal.secret', 'value' => '', 'type' => 'string', 'group' => 'payments', 'label' => 'PayPal Secret'],
            ['key' => 'maintenance.mode', 'value' => '0', 'type' => 'boolean', 'group' => 'maintenance', 'label' => 'Maintenance Mode'],
            ['key' => 'rate_limit.public', 'value' => '60', 'type' => 'integer', 'group' => 'rate_limits', 'label' => 'Public Rate Limit (per minute)'],
            ['key' => 'rate_limit.auth', 'value' => '10', 'type' => 'integer', 'group' => 'rate_limits', 'label' => 'Auth Rate Limit (per minute)'],
            ['key' => 'whatsapp_min_send_delay_seconds', 'value' => '10', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Min Delay Between Messages (Seconds)'],
            ['key' => 'whatsapp_max_send_delay_seconds', 'value' => '25', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Max Delay Between Messages (Seconds)'],
            ['key' => 'whatsapp_batch_pause_every', 'value' => '20', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Pause Batch Size (Messages)'],
            ['key' => 'whatsapp_batch_pause_duration_seconds', 'value' => '45', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Pause Batch Duration (Seconds)'],
            ['key' => 'whatsapp_max_messages_per_hour', 'value' => '100', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Max Messages Per Hour'],
            ['key' => 'whatsapp_max_messages_per_day', 'value' => '1000', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Max Messages Per Day'],
            ['key' => 'whatsapp_same_recipient_cooldown_seconds', 'value' => '60', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Same Recipient Cooldown (Seconds)'],
            ['key' => 'whatsapp_warmup_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'whatsapp_antiban', 'label' => 'Enable Auto Warm-Up for New Sessions'],
            ['key' => 'whatsapp_warmup_base_daily', 'value' => '100', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Warm-Up Base Daily Messages'],
            ['key' => 'whatsapp_warmup_increment_amount', 'value' => '50', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Warm-Up Increment (Messages)'],
            ['key' => 'whatsapp_warmup_increment_every_days', 'value' => '2', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Warm-Up Increment Interval (Days)'],
            ['key' => 'whatsapp_warmup_max_daily', 'value' => '1000', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Warm-Up Max Daily Messages'],
            ['key' => 'whatsapp_warmup_hours_per_day', 'value' => '12', 'type' => 'integer', 'group' => 'whatsapp_antiban', 'label' => 'Warm-Up Distribution Hours Per Day'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::set($setting['key'], $setting['value'], $setting['type'], $setting['group'], $setting['label']);
        }
    }
}
