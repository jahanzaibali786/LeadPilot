<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LeadsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    public function __construct(private Builder $builder) {}
    public function query() { return $this->builder->with(['campaign','service']); }
    public function headings(): array { return ['Campaign','Service','Business Name','Category','Phone','WhatsApp','Address','City','Province','Rating','Reviews','Website Status','Website','Google Maps','Score','Quality','Status','Opportunity','Match Reason','Suggested Offer','WhatsApp English','WhatsApp Localized','Email Subject','Email Body','Source','Notes','Follow-up Date']; }
    public function map($lead): array { return [$lead->campaign?->title,$lead->service?->service_name,$lead->business_name,$lead->business_category,$lead->formatted_phone,$lead->whatsapp_number,$lead->address,$lead->city,$lead->province,$lead->rating,$lead->total_reviews,$lead->website_status,$lead->website,$lead->google_maps_url,$lead->lead_score,$lead->lead_quality,$lead->board_status,$lead->opportunity_type,$lead->match_reason,$lead->suggested_offer,$lead->whatsapp_message_english,$lead->whatsapp_message_roman_urdu,$lead->email_subject,$lead->email_body,$lead->source_name,$lead->notes,optional($lead->follow_up_date)->format('Y-m-d')]; }
    public function registerEvents(): array { return [AfterSheet::class=>function(AfterSheet $event){ $sheet=$event->sheet->getDelegate(); $sheet->freezePane('A2'); $sheet->setAutoFilter($sheet->calculateWorksheetDimension()); $sheet->getStyle('A1:AA1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF'); $sheet->getStyle('A1:AA1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF102A43'); $sheet->getStyle('A:AA')->getAlignment()->setVertical('top')->setWrapText(true); for($row=2;$row<=$sheet->getHighestRow();$row++){ $quality=$sheet->getCell("P$row")->getValue(); $color=$quality==='Hot'?'FFC6EFCE':($quality==='Warm'?'FFFFEB9C':'FFE7E9ED'); $sheet->getStyle("P$row")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color); if($sheet->getCell("L$row")->getValue()==='No Website') $sheet->getStyle("L$row")->getFont()->getColor()->setARGB('FFC0392B'); } }]; }
}
