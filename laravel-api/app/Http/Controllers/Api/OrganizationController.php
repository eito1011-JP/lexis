<?php

namespace App\Http\Controllers\Api;

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
        $this->createOrganizationUseCase->execute(
            $request->organization_uuid,
            $request->organization_name,
            $request->token
        );

        return response()->json();
    }
}


