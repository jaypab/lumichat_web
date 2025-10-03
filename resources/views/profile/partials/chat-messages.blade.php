@foreach($chats as $c)
    <div class="{{ $c->sender==='user'?'text-right':'text-left' }}">
        <span class="{{ $c->sender==='user'?'bubble-user':'bubble-bot' }}">
            {{ $c->message }}
        </span>
    </div>
@endforeach
