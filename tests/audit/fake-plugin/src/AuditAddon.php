<?php

namespace Audit\Directory;

use App\Models\Server;
use App\Models\User;
use Simplify\Core\Addons\Addon;
use Simplify\Core\Addons\Button;

// Puts the account page in Simplify's account menu, as Server Maker does with its Steam account, and gives a suspended
// server a main link, as Hosting does with Pay invoice.
final class AuditAddon extends Addon
{
    public function id(): string
    {
        return 'audit-directory';
    }

    public function name(): string
    {
        return 'Audit Directory';
    }

    public function accountPages(User $user): array
    {
        return [new Button('Directory account', AccountPage::getUrl(panel: 'app'), 'tabler-address-book')];
    }

    public function serverLinks(Server $server): array
    {
        return $server->status?->value === 'suspended' ? [new Button('Pay invoice', 'https://billing.example.com/pay', 'tabler-receipt', primary: true)] : [];
    }
}
