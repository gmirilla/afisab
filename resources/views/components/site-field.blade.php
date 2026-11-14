@props(['name', 'label', 'type' => 'text', 'required' => false, 'wide' => false, 'hint' => null, 'value' => null, 'options' => null, 'placeholder' => null, 'maxlength' => null, 'autocomplete' => null, 'inputmode' => null, 'rows' => 4, 'placeholderOption' => 'Select', 'min' => null, 'step' => null, 'errorKey' => null])
@php
    $dotted = rtrim(str_replace(['][', '[', ']'], ['.', '.', ''], $name), '.');
    $id = str_replace('.', '_', $dotted);
    $error = $errors->first($errorKey ?: $dotted);
    $current = old($dotted, $value);
    $described = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="trade-field {{ $wide ? 'trade-field--wide' : '' }}">
    <label for="{{ $id }}">{{ $label }} @if($required)<span aria-hidden="true">*</span>@endif</label>
    @if($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @required($required) @if($error) aria-invalid="true" @endif @if($described) aria-describedby="{{ $described }}" @endif>
            <option value="">{{ $placeholderOption }}</option>
            @foreach($options as $key => $text)<option value="{{ $key }}" @selected((string) $current === (string) $key)>{{ $text }}</option>@endforeach
        </select>
    @elseif($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required) @if($maxlength) maxlength="{{ $maxlength }}" @endif @if($error) aria-invalid="true" @endif @if($described) aria-describedby="{{ $described }}" @endif>{{ $current }}</textarea>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $current }}" @required($required) @if($maxlength) maxlength="{{ $maxlength }}" @endif @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if($inputmode) inputmode="{{ $inputmode }}" @endif @if($placeholder) placeholder="{{ $placeholder }}" @endif @if($min !== null) min="{{ $min }}" @endif @if($step) step="{{ $step }}" @endif @if($error) aria-invalid="true" @endif @if($described) aria-describedby="{{ $described }}" @endif>
    @endif
    @if($hint)<small id="{{ $id }}-hint">{{ $hint }}</small>@endif
    @if($error)<small class="trade-error" id="{{ $id }}-error">{{ $error }}</small>@endif
</div>
