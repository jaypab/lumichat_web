@extends('layouts.student-registration')

@section('content')
<style>
    html, body {
        overflow: hidden; /* Prevent scrolling */
    }
</style>

<div class="h-screen w-screen bg-gray-100 flex items-center justify-center">
    <div class="bg-white shadow-2xl rounded-3xl p-10 max-w-2xl w-full animate__animated animate__fadeIn">
        <div class="text-center mb-6">
            <img src="{{ asset('images/icons/lock.png') }}" class="mx-auto w-16 h-16 mb-3" alt="Privacy Icon">
            <h1 class="text-2xl font-extrabold text-gray-800">LumiCHAT Privacy Policy</h1>
            <p class="text-sm text-gray-600 mt-1">Your data privacy and security are our top priorities.</p>
        </div>

        <div class="text-gray-700 text-sm space-y-5 leading-relaxed">
            <p>
                This Privacy Policy outlines how LumiChat collects, uses, and protects your personal data. We are committed to keeping your information confidential and used only for mental health support purposes.
            </p>

            <ul class="list-disc list-inside space-y-2">
                <li>Your data is <strong>securely stored</strong> and will never be shared without your consent.</li>
                <li>Only <strong>authorized counseling staff</strong> can access your sensitive information.</li>
                <li>You have the right to <strong>request, update, or delete</strong> your information at any time.</li>
                <li>We follow strict guidelines to comply with data protection policies and ethical counseling practices.</li>
            </ul>

            <p>
                By agreeing to our policy, you help us ensure that your interactions with LumiCHAT are respectful, confidential, and safe.
            </p>

            <p class="text-sm text-gray-600">
                If you have any questions, feel free to contact our school counselor or administrative staff.
            </p>
        </div>

        <div class="text-center mt-8">
            <button
                onclick="redirectToRegister()"
                class="inline-block bg-blue-500 hover:bg-blue-600 text-white text-sm font-semibold py-2 px-6 rounded-lg shadow transition duration-200">
                ← Back to Registration
            </button>
        </div>
    </div>  
</div>

<script>
    function redirectToRegister() {
        window.location.replace("{{ route('register') }}");
    }
</script>
@endsection
