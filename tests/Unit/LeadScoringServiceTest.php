<?php
namespace Tests\Unit;
use App\Services\LeadScoringService;
use PHPUnit\Framework\TestCase;
class LeadScoringServiceTest extends TestCase {
    public function test_strong_business_without_website_is_hot(): void {
        $result=(new LeadScoringService())->score(['website'=>null,'phone'=>'923125551010','whatsapp_number'=>'+923125551010','rating'=>4.5,'total_reviews'=>50,'address'=>'Abbottabad']);
        $this->assertSame(90,$result['lead_score']); $this->assertSame('Hot',$result['lead_quality']);
    }
}
