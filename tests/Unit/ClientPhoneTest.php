<?php

namespace Tests\Unit;

use App\Support\ClientPhone;
use Tests\TestCase;

class ClientPhoneTest extends TestCase
{
    public function test_senegal_local_mobile_becomes_whatsapp_id(): void
    {
        $this->assertSame('221776211688', ClientPhone::toWhatsappId('+221 77 621 16 88'));
        $this->assertSame('221776211688', ClientPhone::toWhatsappId('77 621 16 88'));
    }

    public function test_empty_phone_is_rejected(): void
    {
        $this->assertNull(ClientPhone::toWhatsappId(null));
        $this->assertNull(ClientPhone::toWhatsappId(''));
        $this->assertNull(ClientPhone::toWhatsappId('12'));
    }
}
