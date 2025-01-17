<?php

namespace App\Http\Requests\Template;

use App\Utils\Fetchers\HtmlFetcher;
use App\Watcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{

    public function rules()
    {
        return [
            'xpath_value' => 'nullable|string|min:2|max:191',
            'xpath_stock' => 'nullable|string|max:191',
            'stock_text' => 'nullable|string|max:191',
            'stock_condition' => ['nullable', Rule::in(Watcher::STOCK_CONDITIONS)],
            'client' => 'nullable|in:' . implode(',', HtmlFetcher::CLIENTS),
        ];
    }
}
