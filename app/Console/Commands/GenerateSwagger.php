<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use ReflectionNamedType;

class GenerateSwagger extends Command
{
    protected $signature = 'app:generate-swagger';
    protected $description = 'Génère automatiquement le fichier public/swagger.json avec détection des RequestBody';

    public function handle()
    {
        $this->info('Génération de public/swagger.json...');

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
                elseif (str_contains($action, 'ExpenseController')) $tag = 'Dépenses';
                elseif (str_contains($action, 'DocumentController')) $tag = 'Documents';

                $operation = [
                    'tags' => [$tag],
                    'summary' => $route['name'] ?? $route['uri'],
                    'responses' => [
                        '200' => ['description' => 'Succès'],
                        '401' => ['description' => 'Non authentifié'],
                        '422' => ['description' => 'Erreur de validation'],
                    ],
                ];

                if (str_contains(json_encode($route['middleware']), 'sanctum')) {
                    $operation['security'] = [['bearerAuth' => []]];
                }

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

                if (in_array($lowerMethod, ['post', 'put', 'patch'])) {
                    $requestBody = $this->getRequestBodyFromAction($action);
                    if ($requestBody) {
                        $operation['requestBody'] = $requestBody;
                    }
                }

                $openapi['paths'][$uri][$lowerMethod] = $operation;
            }
        }

        $json = json_encode($openapi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents(public_path('swagger.json'), $json);

        $this->info('Fichier public/swagger.json généré avec succès !');
    }

    private function getRequestBodyFromAction($action)
    {
        if ($action === 'Closure') return null;
        if (!str_contains($action, '@')) return null;

        [$controller, $method] = explode('@', $action);

        try {
            if (!class_exists($controller)) return null;
            $reflection = new ReflectionMethod($controller, $method);
            foreach ($reflection->getParameters() as $parameter) {
                $type = $parameter->getType();
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $className = $type->getName();
                    if (class_exists($className) && (is_subclass_of($className, 'Illuminate\Foundation\Http\FormRequest') || $className === 'Illuminate\Foundation\Http\FormRequest')) {
                        return $this->generateRequestBodyFromFormRequest($className);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error for debugging if needed: $this->error($e->getMessage());
        }

        if (str_contains($action, 'AuthController@login')) {
            return $this->manualRequestBody(['email', 'password']);
        }
        if (str_contains($action, 'AuthController@register')) {
            return $this->manualRequestBody(['name', 'email', 'password', 'password_confirmation', 'role', 'telephone']);
        }

        return null;
    }

    private function generateRequestBodyFromFormRequest($className)
    {
        try {
            $request = new $className();
            if (method_exists($request, 'rules')) {
                // On simule un environnement minimal pour rules()
                $rules = $request->rules();
                $properties = [];
                $required = [];

                foreach ($rules as $field => $rule) {
                    // Nettoyage du nom du champ (ex: items.*.id -> items)
                    $cleanField = explode('.', $field)[0];
                    if (isset($properties[$cleanField])) continue;

                    $properties[$cleanField] = ['type' => 'string'];
                    
                    $ruleStr = is_array($rule) ? implode('|', array_map(fn($r) => is_string($r) ? $r : '', $rule)) : (string)$rule;
                    
                    if (str_contains($ruleStr, 'required')) $required[] = $cleanField;
                    if (str_contains($ruleStr, 'integer') || str_contains($ruleStr, 'numeric')) $properties[$cleanField]['type'] = 'integer';
                    if (str_contains($ruleStr, 'boolean')) $properties[$cleanField]['type'] = 'boolean';
                    if (str_contains($ruleStr, 'email')) $properties[$cleanField]['format'] = 'email';
                    if (str_contains($ruleStr, 'array')) {
                        $properties[$cleanField]['type'] = 'array';
                        $properties[$cleanField]['items'] = ['type' => 'string'];
                    }
                }

                return [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => $properties,
                                'required' => array_values(array_unique($required)),
                            ]
                        ]
                    ]
                ];
            }
        } catch (\Exception $e) {}

        return null;
    }

    private function manualRequestBody($fields)
    {
        $properties = [];
        foreach ($fields as $field) {
            $properties[$field] = ['type' => 'string'];
            if ($field === 'email') $properties[$field]['format'] = 'email';
            if ($field === 'password' || $field === 'password_confirmation') $properties[$field]['format'] = 'password';
        }
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $properties,
                        'required' => $fields,
                    ]
                ]
            ]
        ];
    }
}
