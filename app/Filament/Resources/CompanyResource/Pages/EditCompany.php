<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Método para obtener coordenadas a partir de una dirección usando Nominatim.
     *
     * @param string $address Dirección a buscar.
     * @return void
     */

    public function getCoordinatesFromAddress($address)
    {
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => 1
        ]); 

        $options = [
            "http" => [
                "header" => "User-Agent: tiemply-api/1.0\r\n"
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === FALSE) {
            die("Error al consultar Nominatim.");
        }

        $data = json_decode($response, true);

        if (!empty($data) && isset($data[0]['lat'], $data[0]['lon'])) {

            // Actualiza los campos del formulario
            $currentState = $this->form->getState();
            $currentState['office_latitude'] = $data[0]['lat'];
            $currentState['office_longitude'] = $data[0]['lon'];
            $this->form->fill($currentState);

            Notification::make()
                ->title('Coordenadas actualizadas correctamente.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('No se encontraron coordenadas para la dirección.')
                ->danger()
                ->send();
        }
    }
}
