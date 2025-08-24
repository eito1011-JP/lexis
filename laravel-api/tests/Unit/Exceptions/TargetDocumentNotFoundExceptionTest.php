<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\TargetDocumentNotFoundException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TargetDocumentNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function exception_returns_correct_status_code_and_response(): void
    {
        // Arrange
        $exception = new TargetDocumentNotFoundException('テストメッセージ');
        $request = Request::create('/test', 'GET');

        // Act
        $response = $exception->render($request);

        // Assert
        $this->assertSame(409, $response->getStatusCode());
        $responseData = $response->getData(true);
        $this->assertSame('target_document_not_found', $responseData['result']);
        $this->assertSame('テストメッセージ', $responseData['message']);
    }

    #[Test]
    public function exception_uses_default_message_when_not_provided(): void
    {
        // Arrange
        $exception = new TargetDocumentNotFoundException();
        $request = Request::create('/test', 'GET');

        // Act
        $response = $exception->render($request);

        // Assert
        $responseData = $response->getData(true);
        $this->assertSame('対象のドキュメントが見つかりません', $responseData['message']);
    }

    #[Test]
    public function exception_does_not_report_to_logs(): void
    {
        // Arrange
        $exception = new TargetDocumentNotFoundException();

        // Act & Assert
        $this->assertFalse($exception->report());
    }
}
