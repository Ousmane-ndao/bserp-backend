<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;

class GenerateSwagger extends Command
{
    protected $signature = 'app:generate-swagger';
    protected $description = 'Génère automatiquement le fichier public/swagger.json à partir des routes Laravel';

    public function handle()
    {
        $this->info('Génération de public/swagger.json...');

        // On récupère les routes via l'artisan interne pour être sûr d'avoir le format JSON
        Artisan::call('route:list', ['--json' => true]);
        $routes = json_decode(Artisan::output(), true);

        $openapi = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'BSERP API',
                'description' => 'Documentation de l\'API BSERP (Générée automatiquement)',
                'version' => '1.0.0',
            ],
            'servers' => [
                [
                    'url' => config('app.url'),
                    'description' => 'Serveur actuel',
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT',
                    ],
                ],
            ],
            'paths' => [],
        ];

        foreach ($routes as $route) {
            // On ne garde que les routes API
            if (!str_starts_with($route['uri'], 'api/')) {
                continue;
            }

            $uri = '/' . ltrim($route['uri'], '/');
            $methods = explode('|', $route['method']);

            foreach ($methods as $method) {
                if ($method === 'HEAD') continue;
                
                $lowerMethod = strtolower($method);
                
                if (!isset($openapi['paths'][$uri])) {
                    $openapi['paths'][$uri] = [];
                }

                // Détermination du tag selon le nom du contrôleur
                $tag = 'Général';
                $action = $route['action'];
                if (str_contains($action, 'AuthController')) $tag = 'Authentification';
                elseif (str_contains($action, 'ClientController')) $tag = 'Clients';
                elseif (str_contains($action, 'DossierController')) $tag = 'Dossiers';
                elseif (str_contains($action, 'InvoiceController')) $tag = 'Factures';
                elseif (str_contains($action, 'PaymentController')) $tag = 'Paiements';
                elseif (str_contains($action, 'EmployeeController')) $tag = 'Employés';
                elseif (str_contains($action, 'AccountingController')) $tag = 'Comptabilité';
                elseif (str_contains($action, 'DashboardController')) $tag = 'Tableau de bord';
                elseif (str_contains($action, 'StudentAccountController')) $tag = 'Scolarité';
                elseif (str_contains($action, 'StudentProgressController')) $tag = 'Suivi Pédagogique';

                $operation = [
                    'tags' => [$tag],
                    'summary' => $route['name'] ?? $route['uri'],
                    'responses' => [
                        '200' => ['description' => 'Succès'],
                        '401' => ['description' => 'Non authentifié'],
                        '403' => ['description' => 'Accès refusé'],
                    ],
                ];

                // Sécurité si middleware sanctum présent
                if (str_contains(json_encode($route['middleware']), 'sanctum')) {
                    $operation['security'] = [['bearerAuth' => []]];
                }

                // Paramètres de l'URL
                if (preg_match_all('/\{([^\}]+)\}/', $uri, $matches)) {
                    $operation['parameters'] = [];
                    foreach ($matches[1] as $param) {
                        $operation['parameters'][] = [
                            'name' => $param,
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'string'],
                        ];
                    }
                }

                $openapi['paths'][$uri][$lowerMethod] = $operation;
            }
        }

        $json = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents(public_path('swagger.json'), $json);

        $this->info('Fichier public/swagger.json généré avec succès !');
    }
}
