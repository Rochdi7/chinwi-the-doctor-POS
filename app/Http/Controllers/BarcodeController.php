<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Support\Barcode;
use Symfony\Component\HttpFoundation\Response;

class BarcodeController extends Controller
{
    /** The label image for one article, as a PNG download. */
    public function __invoke(Article $article): Response
    {
        abort_unless((bool) $article->code_barre, 404);

        return response(Barcode::png($article->code_barre, 3, 80), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$article->code_barre.'.png"',
        ]);
    }
}
