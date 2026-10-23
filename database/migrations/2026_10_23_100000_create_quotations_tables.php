<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cotizaciones a clientes y prospectos.
 *
 * - quotations: datos del cliente copiados al cotizar (un prospecto aun no es
 *   cliente; si el RUC ya existe se vincula con client_id), servicio, ruta,
 *   condiciones y estado (borrador, enviada, aceptada, rechazada). "Vencida"
 *   no se guarda: se calcula con la fecha y los dias de validez.
 * - quotation_items: lineas de tarifa con su moneda y si estan afectas a IGV
 *   (el flete internacional es inafecto; los gastos locales, afectos).
 * - app_settings: datos de la empresa para los documentos (RUC, direccion,
 *   contacto, cuentas bancarias) y las condiciones por defecto de las cotizaciones.
 */
return new class extends Migration
{
    public const DEFAULT_CONDITIONS = "Validez: 7 días calendario. Sujeto a variaciones tarifarias (GRI, BAF, etc.) y disponibilidad de equipo/espacio.\n\n"
        ."Pesos/Medidas: Tarifas en base al peso/volumen declarado (relación W/M). Aplica solo para carga general (Dry).\n\n"
        ."Exclusiones: NO incluye impuestos aduaneros, seguro de carga, gastos por aforos físicos, ni sobrestadía/almacenaje (Demurrage/Detention).\n\n"
        ."Responsabilidad: FORCEX LOGISTICS actúa como Agente de Carga. Se recomienda contratar seguro puerta a puerta.";

    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('comercial_id')->constrained('users');

            // Cliente o prospecto (copiado al cotizar: el PDF no cambia si luego cambia la ficha)
            $table->string('ruc', 11)->index();
            $table->string('razon_social');
            $table->string('direccion')->nullable();
            $table->string('contacto_nombre')->nullable();
            $table->string('contacto_correo')->nullable();
            $table->string('contacto_telefono', 30)->nullable();

            // Servicio y ruta
            $table->string('tipo_carga', 10);
            $table->string('incoterm', 5)->nullable();
            $table->string('origen')->nullable();
            $table->string('puerto_carga')->nullable();
            $table->string('puerto_descarga')->nullable();
            $table->string('destino')->nullable();
            $table->string('mercancia')->nullable();
            $table->decimal('peso', 12, 2)->nullable();
            $table->decimal('volumen', 12, 3)->nullable();
            $table->string('contenedores')->nullable();

            // Operacion
            $table->string('naviera')->nullable();
            $table->string('tiempo_transito', 60)->nullable();
            $table->string('frecuencia', 60)->nullable();
            $table->unsignedSmallInteger('dias_libres')->nullable();

            // Condiciones y estado
            $table->date('fecha');
            $table->unsignedSmallInteger('validez_dias')->default(7);
            $table->text('condiciones')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('borrador')->index();
            $table->timestamp('enviada_en')->nullable();
            $table->foreignId('enviada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ultimo_canal', 20)->nullable();
            $table->timestamp('respondida_en')->nullable();
            $table->timestamps();

            $table->index(['comercial_id', 'estado']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_concept_id')->nullable()->constrained()->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('precio_unitario', 12, 2);
            $table->string('moneda', 3);
            $table->boolean('afecto_igv')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('empresa_ruc', 11)->nullable();
            $table->string('empresa_direccion')->nullable();
            $table->string('empresa_telefono', 60)->nullable();
            $table->string('empresa_correo')->nullable();
            $table->string('empresa_web')->nullable();
            $table->text('cuentas_bancarias')->nullable();
            $table->text('cotizacion_condiciones')->nullable();
            $table->unsignedSmallInteger('cotizacion_validez_dias')->default(7);
        });

        DB::table('app_settings')->whereNull('cotizacion_condiciones')->update(['cotizacion_condiciones' => self::DEFAULT_CONDITIONS]);
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'empresa_ruc', 'empresa_direccion', 'empresa_telefono', 'empresa_correo', 'empresa_web',
                'cuentas_bancarias', 'cotizacion_condiciones', 'cotizacion_validez_dias',
            ]);
        });

        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
