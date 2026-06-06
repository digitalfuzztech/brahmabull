@if($type === 'guest')

    <div>
        <p>Hello Admin,</p>

        <h2>New Website Contact Lead</h2>

        <p>
            <strong>Email:</strong>
            {{ $email }}
        </p>

        <p>
            Someone submitted their email through the BrahmaBull Gaming Club website.
        </p>
    </div>

@else

    <div>

        <p>Hello Admin,</p>

        <h2>
            {{ $name }} has sent a message
        </h2>

        <p>
            <strong>Role:</strong>
            {{ ucfirst($type) }}
        </p>

        <p>
            <strong>Name:</strong>
            {{ $name }}
        </p>

        <p>
            <strong>Email:</strong>
            {{ $email }}
        </p>

        <p>
            <strong>Subject:</strong>
            {{ $subjectLine }}
        </p>

        <hr>

        <p>
            {{ $messageBody }}
        </p>

    </div>

@endif
