<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Commodity;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** The public marketing site. Static corporate pages; only the commodity list comes from the database. */
class SiteController extends Controller
{
    public const CATEGORIES = [
        'grains' => 'Grains',
        'oil_seeds' => 'Oil seeds',
        'tree_crops' => 'Tree crops',
        'cash_crops' => 'Cash crops',
    ];

    public function home(): View
    {
        return view('public.home', ['groups' => $this->commodityGroups()]);
    }

    public function trade(): View
    {
        return view('public.trade', ['groups' => $this->commodityGroups()]);
    }

    public function buyers(): View
    {
        return view('public.buyers', ['commodities' => $this->commodityNames()]);
    }

    public function suppliers(): View
    {
        return view('public.suppliers', ['commodities' => $this->commodityNames()]);
    }

    public function how(): View
    {
        return view('public.how');
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function contact(): View
    {
        return view('public.contact');
    }

    public function privacy(): View
    {
        return view('public.privacy');
    }

    public function terms(): View
    {
        return view('public.terms');
    }

    public function risk(): View
    {
        return view('public.risk');
    }

    /** Promotes the investment programme, so it only exists once counsel has cleared the wording (AFISAB_PUBLIC_INVESTOR_PAGE). */
    public function investors(): View
    {
        abort_unless(config('afisab.public_investor_page'), 404);

        return view('public.investors');
    }

    public function sitemap(): Response
    {
        $pages = ['home', 'public.trade', 'public.buyers', 'public.suppliers', 'public.how', 'public.about', 'public.contact', 'public.privacy', 'public.terms', 'public.risk'];
        if (config('afisab.public_investor_page')) {
            $pages[] = 'public.investors';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($pages as $name) {
            $xml .= '  <url><loc>'.e(route($name)).'</loc></url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $text = "User-agent: *\nDisallow: /admin\nDisallow: /investor\nDisallow: /account\nDisallow: /dashboard\nDisallow: /webhooks\n\nSitemap: ".route('sitemap')."\n";

        return response($text, 200, ['Content-Type' => 'text/plain']);
    }

    /** @return array<string, array{label: string, items: list<string>}> only categories that have active commodities */
    private function commodityGroups(): array
    {
        $groups = [];
        foreach (Commodity::where('is_active', true)->orderBy('name')->get()->groupBy('category') as $category => $items) {
            $groups[$category] = ['label' => self::CATEGORIES[$category] ?? ucfirst(str_replace('_', ' ', $category)), 'items' => $items->pluck('name')->all()];
        }
        // Keep the four known groups in their natural order, any others after.
        uksort($groups, fn ($a, $b) => (array_search($a, array_keys(self::CATEGORIES)) === false ? 99 : array_search($a, array_keys(self::CATEGORIES)))
            <=> (array_search($b, array_keys(self::CATEGORIES)) === false ? 99 : array_search($b, array_keys(self::CATEGORIES))));

        return $groups;
    }

    /** @return list<string> */
    private function commodityNames(): array
    {
        return Commodity::where('is_active', true)->orderBy('name')->pluck('name')->all();
    }
}
