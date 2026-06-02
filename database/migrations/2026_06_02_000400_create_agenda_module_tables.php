<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agenda_events')) {
            Schema::create('agenda_events', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->nullable()->unique();
                $table->string('title', 220);
                $table->text('description')->nullable();
                $table->string('type', 40)->default('compromisso');
                $table->string('status', 30)->default('aberto');
                $table->string('priority', 20)->nullable();
                $table->boolean('is_fatal')->default(false);
                $table->boolean('all_day')->default(false);
                $table->dateTime('start_at');
                $table->dateTime('end_at')->nullable();
                $table->string('location', 220)->nullable();
                $table->unsignedInteger('reminder_minutes')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->unsignedBigInteger('requester_user_id')->nullable();
                $table->unsignedBigInteger('process_id')->nullable();
                $table->unsignedBigInteger('demand_id')->nullable();
                $table->integer('client_id')->nullable();
                $table->unsignedBigInteger('contract_id')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->unsignedBigInteger('completed_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'start_at'], 'idx_agenda_status_start');
                $table->index(['responsible_user_id', 'status'], 'idx_agenda_responsible_status');
                $table->index('process_id', 'idx_agenda_process');

                $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('requester_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        $this->seedModule();
        $this->seedRoutePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_events');

        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')->where('slug', 'agenda')->delete();
        }

        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')->whereIn('route_name', array_keys($this->routePermissions()))->delete();
        }
    }

    private function seedModule(): void
    {
        if (!Schema::hasTable('system_modules')) {
            return;
        }

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'agenda'],
            [
                'name' => 'Agenda',
                'icon_class' => 'fa-solid fa-calendar-days',
                'route_prefix' => '/agenda',
                'is_enabled' => true,
                'sort_order' => 36,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedRoutePermissions(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        foreach ($this->routePermissions() as $routeName => $label) {
            $payload = [
                'group_key' => 'agenda',
                'label' => $label,
            ];

            if (Schema::hasColumn('route_permissions', 'created_at')) {
                $payload['created_at'] = now();
            }
            if (Schema::hasColumn('route_permissions', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            DB::table('route_permissions')->updateOrInsert(['route_name' => $routeName], $payload);
        }
    }

    private function routePermissions(): array
    {
        return [
            'agenda.calendar' => 'Acessar calendario da agenda',
            'agenda.index' => 'Listar compromissos e prazos',
            'agenda.create' => 'Novo compromisso ou prazo',
            'agenda.store' => 'Salvar compromisso ou prazo',
            'agenda.show' => 'Visualizar compromisso ou prazo',
            'agenda.edit' => 'Editar compromisso ou prazo',
            'agenda.update' => 'Atualizar compromisso ou prazo',
            'agenda.complete' => 'Concluir compromisso ou prazo',
            'agenda.delete' => 'Excluir compromisso ou prazo',
        ];
    }
};
