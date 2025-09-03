<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Http\Requests\CreateOrganizationRequest;
use App\UseCases\Organization\CreateOrganizationUseCase;
use Illuminate\Http\JsonResponse;

class OrganizationController extends ApiBaseController
{
    public function __construct(
        private CreateOrganizationUseCase $createOrganizationUseCase
    ) {}

    public function create(CreateOrganizationRequest $request): JsonResponse
    {
        try {
            $result = $this->createOrganizationUseCase->execute(
                $request->organization_uuid,
                $request->organization_name,
                $request->token
            );

            return response()->json([
                'organization' => $result['organization'],
                'user' => $result['user']
            ]);
        } catch (\App\Exceptions\AuthenticationException $e) {
            return $this->sendError(
                $e->getCode(),
                $e->getMessage(),
                $e->getStatusCode(),
                \Psr\Log\LogLevel::WARNING
            );
        } catch (\App\Exceptions\DuplicateExecutionException $e) {
            return $this->sendError(
                $e->getCode(),
                $e->getMessage(),
                $e->getStatusCode(),
                \Psr\Log\LogLevel::WARNING
            );
        } catch (\Exception $e) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                $e->getMessage(),
                ErrorType::STATUS_INTERNAL_ERROR,
                \Psr\Log\LogLevel::WARNING
            );
        }

    }
}


