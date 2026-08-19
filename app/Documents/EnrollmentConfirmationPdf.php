<?php

namespace App\Documents;

use App\Models\Enrollment;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class EnrollmentConfirmationPdf
{
    public function download(Enrollment $enrollment): Response
    {
        $fileName = Str::slug($enrollment->reference_code.'-'.$enrollment->full_name).'.pdf';

        return response($this->render($enrollment), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function render(Enrollment $enrollment): string
    {
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
