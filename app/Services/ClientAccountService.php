<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;
use App\Models\Destination;

class ClientAccountService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function syncForClient(Client $client, Destination $destination, array $data): Account
    {
        $email = isset($data['account_email']) && $data['account_email'] !== ''
            ? (string) $data['account_email']
            : $client->email;

        $account = Account::query()->firstOrNew(['client_id' => $client->id]);
        $account->email = $email;

        if ($destination->isFrance()) {
            if (! empty($data['gmail_password'])) {
                $account->password = $data['gmail_password'];
            }
            if (! empty($data['campus_password'])) {
                $account->campus_password = $data['campus_password'];
            }
            if (! empty($data['parcoursup_password'])) {
                $account->parcoursup_password = $data['parcoursup_password'];
            }
        } else {
            $account->password = null;
            $account->campus_password = null;
            $account->parcoursup_password = null;
        }

        $account->client_id = $client->id;
        $account->save();

        return $account;
    }
}
