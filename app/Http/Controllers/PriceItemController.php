<?php

namespace App\Http\Controllers;

use App\Models\PriceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PriceItemController extends Controller
{
  
    public function index(Request $request)
    {
        $q = trim((string)$request->input('q', ''));
        $active = $request->input('active', '');

        $query = PriceItem::query();

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('title', 'like', "%{$q}%")
                   ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($active !== '') {
            // active = 1 or 0
            $query->where('is_active', (int)$active);
        }

        $items = $query
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(15)
            ->withQueryString();

        return view('price_items.index', compact('items', 'q', 'active'));
    }

    public function create()
    {
        return view('price_items.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'title'       => 'required|string|max:255',
                'description' => 'nullable|string',
                'price'       => 'required|numeric|min:0',
                'sort_order'  => 'nullable|integer|min:0',
                'is_active'   => 'nullable|boolean',
            ],
            [
                'title.required' => 'Ο τίτλος είναι υποχρεωτικός.',
                'price.required' => 'Η τιμή είναι υποχρεωτική.',
            ]
        );

        PriceItem::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'],
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => (bool)($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('price_items.index')
            ->with('success', 'Το στοιχείο προστέθηκε στον τιμοκατάλογο.');
    }

    public function edit(PriceItem $price_item)
    {
        return view('price_items.edit', ['item' => $price_item]);
    }

    public function update(Request $request, PriceItem $price_item)
    {
        $data = $request->validate(
            [
                'title'       => 'required|string|max:255',
                'description' => 'nullable|string',
                'price'       => 'required|numeric|min:0',
                'sort_order'  => 'nullable|integer|min:0',
                'is_active'   => 'nullable|boolean',
            ]
        );

        $price_item->update([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'],
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => (bool)($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('price_items.index')
            ->with('success', 'Το στοιχείο ενημερώθηκε.');
    }

    public function destroy(PriceItem $price_item)
    {
        $price_item->delete();

        return redirect()
            ->route('price_items.index')
            ->with('success', 'Το στοιχείο διαγράφηκε.');
    }

    // Optional show (αν δεν το θες, δεν το χρησιμοποιείς)
    public function show(PriceItem $price_item)
    {
        return view('price_items.show', ['item' => $price_item]);
    }
}
