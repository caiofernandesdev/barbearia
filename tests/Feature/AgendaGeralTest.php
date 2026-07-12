<?php

namespace Tests\Feature;

use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AgendaGeralTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ve_a_pagina_agenda_com_seletor_de_profissional(): void
    {
        app()->forgetInstance('current_tenant');
        $tenant = Tenant::forceCreate(['slug' => 'ag-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $tenant);

        Profissional::forceCreate(['nome' => 'Ana', 'tenant_id' => $tenant->id]);
        Profissional::forceCreate(['nome' => 'Bruno', 'tenant_id' => $tenant->id]);

        $admin = User::forceCreate([
            'name' => 'Dono', 'email' => 'ag-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'admin', 'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/agenda-geral')
            ->assertOk()
            ->assertSee('Ver agenda de')
            ->assertSee('Ana')
            ->assertSee('Bruno');
    }

    public function test_barbeiro_nao_acessa_a_agenda_geral(): void
    {
        app()->forgetInstance('current_tenant');
        $tenant = Tenant::forceCreate(['slug' => 'ag-'.uniqid(), 'nome' => 'Salão']);
        app()->instance('current_tenant', $tenant);

        $barbeiro = User::forceCreate([
            'name' => 'Prof', 'email' => 'ag-'.uniqid().'@x.com',
            'password' => Hash::make('secret123'), 'role' => 'barbeiro', 'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($barbeiro, 'admin')
            ->get('/admin/agenda-geral')
            ->assertForbidden();
    }
}
