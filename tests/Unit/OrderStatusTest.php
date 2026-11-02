<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    public function test_le_cycle_de_vie_suit_l_ordre_attendu(): void
    {
        $this->assertTrue(OrderStatus::PendingConfirmation->canTransitionTo(OrderStatus::Confirmed));
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::InProduction));
        $this->assertTrue(OrderStatus::InProduction->canTransitionTo(OrderStatus::Ready));
        $this->assertTrue(OrderStatus::Ready->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_les_etapes_ne_peuvent_pas_etre_sautees_ni_inversees(): void
    {
        $this->assertFalse(OrderStatus::PendingConfirmation->canTransitionTo(OrderStatus::Delivered));
        $this->assertFalse(OrderStatus::Delivered->canTransitionTo(OrderStatus::Confirmed));
        $this->assertFalse(OrderStatus::Cancelled->canTransitionTo(OrderStatus::Confirmed));
        $this->assertFalse(OrderStatus::Ready->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_l_annulation_est_possible_jusqu_a_la_confection(): void
    {
        $this->assertTrue(OrderStatus::PendingConfirmation->canTransitionTo(OrderStatus::Cancelled));
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Cancelled));
        $this->assertTrue(OrderStatus::InProduction->canTransitionTo(OrderStatus::Cancelled));
    }

    public function test_les_etats_finaux_sont_identifies(): void
    {
        $this->assertTrue(OrderStatus::Delivered->isFinal());
        $this->assertTrue(OrderStatus::Cancelled->isFinal());
        $this->assertFalse(OrderStatus::PendingConfirmation->isFinal());
    }

    public function test_les_libelles_francais_sont_exposes(): void
    {
        $options = OrderStatus::options();

        $this->assertSame('En attente de confirmation', $options['pending_confirmation']);
        $this->assertSame('Livrée', $options['delivered']);
        $this->assertCount(6, $options);
    }
}
