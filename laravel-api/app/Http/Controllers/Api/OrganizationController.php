<?php

namespace App\Http\Controllers\Api;

use App\Consts\ErrorType;
use App\Exceptions\AuthenticationException;
use App\Exceptions\DuplicateExecutionException;
use App\Http\Requests\CreateOrganizationRequest;
use App\UseCases\Organization\CreateOrganizationUseCase;
use Exception;
use Illuminate\Http\JsonResponse;
use Psr\Log\LogLevel;

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
                'user' => $result['user'],
            ]);
        } catch (AuthenticationException) {
            return $this->sendError(
                ErrorType::CODE_AUTHENTICATION_FAILED,
                __('errors.MSG_AUTHENTICATION_FAILED'),
                ErrorType::STATUS_AUTHENTICATION_FAILED,
            );
        } catch (DuplicateExecutionException) {
            return $this->sendError(
                ErrorType::CODE_DUPLICATE_EXECUTION,
                __('errors.MSG_DUPLICATE_EXECUTION'),
                ErrorType::STATUS_DUPLICATE_EXECUTION,
            );
        } catch (Exception) {
            return $this->sendError(
                ErrorType::CODE_INTERNAL_ERROR,
                __('errors.MSG_INTERNAL_ERROR'),
                ErrorType::STATUS_INTERNAL_ERROR,
                LogLevel::ERROR,
            );
        }
    }
}
