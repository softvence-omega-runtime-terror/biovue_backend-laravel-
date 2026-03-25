<?php

namespace App\Imports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Storage;

class ProductsImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        $imageName = null;
        if (!empty($row['image'])) {
            $imageContent = file_get_contents($row['image']);
            $imageName = 'product_' . time() . '_' . uniqid() . '.jpg';
            Storage::disk('public')->put('products/' . $imageName, $imageContent);
        }
        return new Product([
            'supplier_id'  => auth()->id(), 
            'name'         => $row['name'],
            'description'  => $row['description'],
            'category'     => $row['category'],
            'price'        => $row['price'],
            'redirect_url' => $row['redirect_url'],
            'status'       => $row['status'] ?? 'draft',
            'image'        => $imageName ? 'products/' . $imageName : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'category' => 'required|in:fitness,nutrition,supplements',
            'price'    => 'required|numeric',
            'status'   => 'nullable|in:draft,published',
            'image'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ];
    }
}