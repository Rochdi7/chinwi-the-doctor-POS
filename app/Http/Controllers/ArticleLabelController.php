<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Setting;
use App\Support\Barcode;
use Illuminate\Contracts\View\View;

class ArticleLabelController extends Controller
{
    /**
     * The shelf label as a page: barcode large and pixel-exact, name and
     * price. Print it, or hold the scanner up to it on screen. A big white
     * page with wide bars is what a presentation scanner reads from an LCD;
     * a small preview inside a form is not.
     */
    public function __invoke(Article $article): View
    {
        abort_unless((bool) $article->code_barre, 404);

        return view('labels.article', [
            'article' => $article,
            'societe' => Setting::get('societe_nom'),
            // widthFactor 6: a 0.33 mm module at typical 200 dpi label
            // printers and ~6 screen pixels per module on a monitor.
            'barcode' => 'data:image/png;base64,'.base64_encode(Barcode::png($article->code_barre, 6, 140)),
        ]);
    }
}
