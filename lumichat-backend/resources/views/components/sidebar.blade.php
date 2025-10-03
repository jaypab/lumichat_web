<div id="sidebar" class="p-6 space-y-4">
    <div class="text-white text-lg font-semibold flex items-center gap-2">
        <img src="/logo.png" class="w-6 h-6" alt="Logo">
        LumiCHAT
    </div>
    <nav class="space-y-2">
        <a href="{{ route('chat.show') }}" class="text-white hover:text-indigo-300 block">Home</a>
        <a href="{{ route('profile.edit') }}" class="text-white hover:text-indigo-300 block">Profile</a>
        <a href="{{ route('chat.history') }}" class="text-white hover:text-indigo-300 block">Chat History</a>
        <a href="{{ route('appointments') }}" class="text-white hover:text-indigo-300 block">Appointments</a>
        <a href="{{ route('settings') }}" class="text-white hover:text-indigo-300 block">Settings</a>
    </nav>
</div>
