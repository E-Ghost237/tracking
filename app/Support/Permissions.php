<?php

namespace App\Support;

/**
 * Permission slugs. Roles and their permissions live in the database (spec section 2.1),
 * these constants only keep slugs consistent in code.
 */
final class Permissions
{
    public const ADMIN_ACCESS = 'admin.access';

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const CUSTOMERS_VIEW = 'customers.view';

    public const SHIPMENTS_VIEW = 'shipments.view';

    public const SHIPMENTS_MANAGE = 'shipments.manage';

    public const EVENTS_CREATE = 'events.create';

    public const EVENTS_IMPORT = 'events.import';

    public const EVENTS_AFTER_DELIVERY = 'events.after_delivery';

    public const ORDERS_VIEW = 'orders.view';

    public const ORDERS_REFUND = 'orders.refund';

    public const PAYMENT_METHODS_MANAGE = 'payment_methods.manage';

    public const PROOFS_VIEW = 'proofs.view';

    public const PROOFS_REVIEW = 'proofs.review';

    public const PROOFS_CORRECT = 'proofs.correct';

    public const GIFT_CARDS_REVEAL = 'gift_cards.reveal';

    public const RATES_MANAGE = 'rates.manage';

    public const CARRIERS_MANAGE = 'carriers.manage';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_MANAGE = 'roles.manage';

    public const TICKETS_MANAGE = 'tickets.manage';

    public const CLAIMS_MANAGE = 'claims.manage';

    public const CONTENT_MANAGE = 'content.manage';

    public const NOTIFICATIONS_MANAGE = 'notifications.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const AUDIT_VIEW = 'audit.view';

    /**
     * @return array<string, array{name: string, group: string}>
     */
    public static function all(): array
    {
        return [
            self::ADMIN_ACCESS => ['name' => 'Access the back-office', 'group' => 'general'],
            self::DASHBOARD_VIEW => ['name' => 'View dashboard', 'group' => 'general'],
            self::CUSTOMERS_VIEW => ['name' => 'View customers', 'group' => 'customers'],
            self::SHIPMENTS_VIEW => ['name' => 'View shipments', 'group' => 'shipping'],
            self::SHIPMENTS_MANAGE => ['name' => 'Edit shipments', 'group' => 'shipping'],
            self::EVENTS_CREATE => ['name' => 'Add tracking events', 'group' => 'shipping'],
            self::EVENTS_IMPORT => ['name' => 'Import tracking events', 'group' => 'shipping'],
            self::EVENTS_AFTER_DELIVERY => ['name' => 'Add events after delivery', 'group' => 'shipping'],
            self::ORDERS_VIEW => ['name' => 'View orders', 'group' => 'orders'],
            self::ORDERS_REFUND => ['name' => 'Refunds and credit notes', 'group' => 'orders'],
            self::PAYMENT_METHODS_MANAGE => ['name' => 'Configure payment methods', 'group' => 'payments'],
            self::PROOFS_VIEW => ['name' => 'View payment proofs', 'group' => 'payments'],
            self::PROOFS_REVIEW => ['name' => 'Approve or reject proofs', 'group' => 'payments'],
            self::PROOFS_CORRECT => ['name' => 'Record review corrections', 'group' => 'payments'],
            self::GIFT_CARDS_REVEAL => ['name' => 'Reveal gift card codes', 'group' => 'payments'],
            self::RATES_MANAGE => ['name' => 'Manage rates', 'group' => 'pricing'],
            self::CARRIERS_MANAGE => ['name' => 'Manage carriers', 'group' => 'shipping'],
            self::USERS_MANAGE => ['name' => 'Manage users', 'group' => 'access'],
            self::ROLES_MANAGE => ['name' => 'Manage roles', 'group' => 'access'],
            self::TICKETS_MANAGE => ['name' => 'Handle support tickets', 'group' => 'support'],
            self::CLAIMS_MANAGE => ['name' => 'Handle claims', 'group' => 'support'],
            self::CONTENT_MANAGE => ['name' => 'Manage content', 'group' => 'content'],
            self::NOTIFICATIONS_MANAGE => ['name' => 'Manage email templates', 'group' => 'content'],
            self::SETTINGS_MANAGE => ['name' => 'Manage settings', 'group' => 'system'],
            self::AUDIT_VIEW => ['name' => 'View audit log', 'group' => 'system'],
        ];
    }

    /**
     * Default role matrix (spec section 2).
     *
     * @return array<string, array{name: string, is_staff: bool, permissions: array<int, string>}>
     */
    public static function defaultRoles(): array
    {
        return [
            'customer' => ['name' => 'Customer', 'is_staff' => false, 'permissions' => []],
            'business_customer' => ['name' => 'Business customer', 'is_staff' => false, 'permissions' => []],
            'support_agent' => [
                'name' => 'Support agent',
                'is_staff' => true,
                'permissions' => [
                    self::ADMIN_ACCESS, self::DASHBOARD_VIEW, self::CUSTOMERS_VIEW, self::SHIPMENTS_VIEW,
                    self::SHIPMENTS_MANAGE, self::EVENTS_CREATE, self::EVENTS_IMPORT, self::TICKETS_MANAGE,
                    self::CLAIMS_MANAGE,
                ],
            ],
            'payment_verifier' => [
                'name' => 'Payment verifier',
                'is_staff' => true,
                'permissions' => [
                    self::ADMIN_ACCESS, self::DASHBOARD_VIEW, self::ORDERS_VIEW, self::PROOFS_VIEW,
                    self::PROOFS_REVIEW, self::GIFT_CARDS_REVEAL,
                ],
            ],
            'admin' => [
                'name' => 'Admin',
                'is_staff' => true,
                'permissions' => array_keys(self::all()),
            ],
        ];
    }
}
