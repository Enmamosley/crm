<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index()
    {
        $tags = Tag::withCount('clients')->get();
        return view('admin.tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:50|unique:tags,name',
            'color' => 'required|string|max:7',
        ]);

        Tag::create($validated);

        return back()->with('success', 'Etiqueta creada.');
    }

    public function update(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:50|unique:tags,name,' . $tag->id,
            'color' => 'required|string|max:7',
        ]);

        $tag->update($validated);

        return back()->with('success', 'Etiqueta actualizada.');
    }

    public function destroy(Tag $tag)
    {
        $tag->clients()->detach();
        $tag->delete();

        return back()->with('success', 'Etiqueta eliminada.');
    }
}
