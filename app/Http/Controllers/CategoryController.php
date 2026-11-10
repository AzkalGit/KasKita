<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Models\Organization;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Organization $organization)
    {
        Gate::authorize('manageSettings', $organization);

        // Transaksi yang sudah dihapus (soft delete) tetap dihitung: foreign key tetap menjaganya
        $categories = $organization->categories()
            ->withCount(['transactions' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('categories.index', compact('organization', 'categories'));
    }

    public function store(CategoryRequest $request, Organization $organization): RedirectResponse
    {
        $category = $organization->categories()->create([
            'name' => $request->validated('name'),
            'is_system' => false,
        ]);

        ActivityLogger::record($organization, $request->user(), 'category.created', $category, [
            'name' => $category->name,
        ]);

        return $this->done($organization, "Kategori {$category->name} ditambahkan.");
    }

    public function update(CategoryRequest $request, Organization $organization, Category $category): RedirectResponse
    {
        if ($category->is_system) {
            return $this->refuse($organization, 'Kategori bawaan sistem tidak bisa diubah.');
        }

        $from = $category->name;
        $to = $request->validated('name');

        if ($from === $to) {
            return $this->done($organization, 'Nama kategori tidak berubah.');
        }

        $category->update(['name' => $to]);

        ActivityLogger::record($organization, $request->user(), 'category.renamed', $category, [
            'from' => $from,
            'to' => $to,
        ]);

        return $this->done($organization, "Kategori diubah menjadi {$to}.");
    }

    public function destroy(Request $request, Organization $organization, Category $category): RedirectResponse
    {
        Gate::authorize('manageSettings', $organization);

        if ($category->is_system) {
            return $this->refuse($organization, 'Kategori bawaan sistem tidak bisa dihapus.');
        }

        if ($category->transactions()->withTrashed()->exists()) {
            return $this->refuse($organization, "Kategori {$category->name} sudah dipakai transaksi, jadi tidak bisa dihapus.");
        }

        $name = $category->name;
        $category->delete();

        ActivityLogger::record($organization, $request->user(), 'category.deleted', $category, [
            'name' => $name,
        ]);

        return $this->done($organization, "Kategori {$name} dihapus.");
    }

    private function done(Organization $organization, string $message): RedirectResponse
    {
        return redirect()->route('categories.index', $organization)->with('status', $message);
    }

    private function refuse(Organization $organization, string $message): RedirectResponse
    {
        return redirect()->route('categories.index', $organization)->withErrors(['category' => $message]);
    }
}
