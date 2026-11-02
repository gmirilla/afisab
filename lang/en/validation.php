<?php

/*
 * Plain-English wording for what investors and staff see when a form is refused. Only what differs from Laravel's own
 * messages is listed here: these are merged over the framework's. Add a field to 'custom' to word its errors exactly.
 */
return [
    'required' => 'Please fill in :attribute.',
    'accepted' => 'Please tick the box to continue.',
    'email' => 'Please enter a valid email address.',
    'date' => 'Please enter a valid date.',
    'digits' => ':Attribute must be exactly :digits digits.',
    'exists' => 'Please choose one of the options shown for :attribute.',
    'in' => 'Please choose one of the options shown for :attribute.',
    'enum' => 'Please choose one of the options shown for :attribute.',
    'confirmed' => 'The two :attribute entries do not match.',
    'unique' => 'That :attribute is already in use.',
    'dimensions' => 'That picture is not an accepted size.',
    'image' => 'Please upload a picture (JPG or PNG).',
    'file' => 'Please attach a file.',
    'uploaded' => 'The file could not be uploaded. It may be too large; please try a smaller one.',
    'mimes' => 'Please upload a file of one of these types: :values.',
    'mimetypes' => 'Please upload a file of one of these types: :values.',
    'max' => [
        'file' => 'That file is too large. The most allowed is :max KB.',
        'string' => ':Attribute is too long (at most :max characters).',
        'numeric' => ':Attribute cannot be more than :max.',
        'array' => 'Please choose no more than :max items.',
    ],
    'min' => [
        'file' => 'That file is too small.',
        'string' => ':Attribute is too short (at least :min characters).',
        'numeric' => ':Attribute cannot be less than :min.',
        'array' => 'Please choose at least :min items.',
    ],
    'before' => 'Please enter an earlier date for :attribute.',
    'before_or_equal' => 'The date for :attribute cannot be in the future.',
    'after_or_equal' => 'Please enter a later date for :attribute.',

    // Exact wording for the fields people trip over most.
    'custom' => [
        'proof' => [
            'required' => 'Please attach your transfer receipt.',
            'mimes' => 'Please upload a JPG, PNG or PDF of your receipt.',
            'max' => 'That receipt is too large. Please upload a file under 4 MB.',
            'file' => 'Please attach your transfer receipt.',
        ],
        'id_document' => [
            'required' => 'Please upload a photo or scan of your government ID.',
            'mimes' => 'Please upload a JPG, PNG or PDF of your ID.',
            'max' => 'That file is too large. Please upload one under 4 MB.',
        ],
        'photo' => [
            'mimes' => 'Please upload a JPG, PNG or WebP picture.',
            'max' => 'That picture is too large. Please upload one under 4 MB.',
        ],
        'signature' => [
            'mimes' => 'Please upload a PNG or JPG of the signature.',
            'max' => 'That signature image is too large. Please upload one under 1 MB.',
        ],
        'stamp' => [
            'mimes' => 'Please upload a PNG or JPG of the stamp.',
            'max' => 'That stamp image is too large. Please upload one under 2 MB.',
        ],
        'investment_plan_id' => [
            'required' => 'Please choose a plan.',
            'exists' => 'That plan is not available. Please choose one of the plans shown.',
        ],
        'profit_frequency' => [
            'enum' => 'Please choose one of the profit payment options shown.',
        ],
        'accept_terms' => [
            'accepted' => 'Please confirm that you have read and agree to the Investment Agreement.',
        ],
        'nin' => [
            'required' => 'Please enter your NIN.',
            'digits' => 'Your NIN must be exactly 11 digits.',
        ],
        'account_number' => [
            'digits' => 'Your account number must be exactly 10 digits.',
        ],
        'date_of_birth' => [
            'before' => 'You must be at least 18 years old to invest.',
        ],
        'password' => [
            'current_password' => 'Your password is incorrect.',
            'confirmed' => 'The two passwords do not match.',
        ],
    ],

    'attributes' => [
        'investment_plan_id' => 'the plan',
        'profit_frequency' => 'the profit payment option',
        'accept_terms' => 'the agreement',
        'date_of_birth' => 'your date of birth',
        'account_number' => 'your account number',
        'account_name' => 'your account name',
        'bank_name' => 'your bank name',
        'id_document' => 'your ID',
        'nin' => 'your NIN',
        'phone' => 'your phone number',
        'address' => 'your address',
        'name' => 'your name',
        'email' => 'your email address',
        'password' => 'your password',
        'amount' => 'the amount',
        'received_on' => 'the date received',
        'proof' => 'your receipt',
        'photo' => 'your picture',
        'note' => 'a note',
        'reason' => 'a reason',
        'message' => 'your message',
        'company' => 'your company name',
        'commodity' => 'a commodity',
    ],
];
