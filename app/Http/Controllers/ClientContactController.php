<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Contactos del cliente (1 a N). Cada contacto indica si recibe los avisos
 * de la carga (tracking) o los de facturacion.
 */
class ClientContactController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('manageContacts', $client);

        $data = $request->validate($this->rules($client));

        DB::transaction(function () use ($client, $data, $request) {
            $contact = $client->contacts()->create(ClientController::normalizeContact($data));

            if ($request->boolean('es_principal') || $client->contacts()->count() === 1) {
                $this->makePrimary($contact);
            }
        });

        return $this->backToClient($client, 'Contacto agregado correctamente.');
    }

    public function update(Request $request, ClientContact $contact): RedirectResponse
    {
        $client = $contact->client;
        Gate::authorize('manageContacts', $client);

        $data = $request->validate($this->rules($client, $contact));

        DB::transaction(function () use ($contact, $data, $request) {
            $contact->update(ClientController::normalizeContact($data));

            if ($request->boolean('es_principal')) {
                $this->makePrimary($contact);
            }
        });

        return $this->backToClient($client, 'Contacto actualizado correctamente.');
    }

    public function destroy(ClientContact $contact): RedirectResponse
    {
        $client = $contact->client;
        Gate::authorize('manageContacts', $client);

        if ($client->contacts()->count() <= 1) {
            return $this->backToClient($client)->withErrors([
                'contacto' => 'El cliente debe conservar al menos un contacto.',
            ]);
        }

        DB::transaction(function () use ($contact, $client) {
            $wasPrimary = $contact->es_principal;
            $contact->delete();

            if ($wasPrimary) {
                $this->makePrimary($client->contacts()->first());
            }
        });

        return $this->backToClient($client, 'Contacto eliminado correctamente.');
    }

    private function rules(Client $client, ?ClientContact $contact = null): array
    {
        $rules = ClientController::contactRules();
        $rules['correo'][] = Rule::unique('client_contacts', 'correo')
            ->where('client_id', $client->id)
            ->ignore($contact);
        $rules['es_principal'] = ['nullable', 'boolean'];

        return $rules;
    }

    private function makePrimary(?ClientContact $contact): void
    {
        if (! $contact) {
            return;
        }

        ClientContact::where('client_id', $contact->client_id)
            ->whereKeyNot($contact->id)
            ->update(['es_principal' => false]);

        $contact->forceFill(['es_principal' => true])->save();
    }

    private function backToClient(Client $client, ?string $status = null): RedirectResponse
    {
        $redirect = redirect()->to(route('clientes.show', $client).'#contactos');

        return $status ? $redirect->with('status', $status) : $redirect;
    }
}
