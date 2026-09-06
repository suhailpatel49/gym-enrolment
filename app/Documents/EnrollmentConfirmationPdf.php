<?php

namespace App\Documents;

use App\Models\Enrollment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnrollmentConfirmationPdf
{
    public function download(Enrollment $enrollment): StreamedResponse
    {
        $fileName = Str::slug($enrollment->reference_code.'-'.$enrollment->full_name).'.pdf';
        $contents = $this->render($enrollment);

        return response()->streamDownload(static function () use ($contents): void {
            echo $contents;
        }, $fileName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function render(Enrollment $enrollment): string
    {
        abort_unless($enrollment->approval_status === 'approved', 403);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.enrollment-confirmation', [
            'enrollment' => $enrollment,
        ])->render());
        $pdf->setPaper('a4');
        $pdf->render();

        return $pdf->output();
    }
}
