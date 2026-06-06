<?php
namespace Tests\Unit;
use App\Services\PhoneNormalizerService;
use PHPUnit\Framework\TestCase;
class PhoneNormalizerServiceTest extends TestCase {
    public function test_it_normalizes_common_pakistani_mobile_formats(): void {
        $service=new PhoneNormalizerService();
        foreach(['03123456789','+923123456789','923123456789'] as $number) $this->assertSame('+923123456789',$service->normalize($number)['whatsapp_number']);
    }
}
