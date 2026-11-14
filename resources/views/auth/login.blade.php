<x-site-layout title="Sign in" description="Sign in to your AFISAB account. Investors and AFISAB staff use the same sign-in.">
  <main id="main-content" class="investor-main">
    <div class="wrap investor-grid">
      <div class="investor-intro">
        <nav class="breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><span aria-current="page">Sign in</span></nav>
        <p class="eyebrow">INVESTORS &amp; STAFF</p>
        <h1 class="display">Sign in to <em>AFISAB.</em></h1>
        <p>One sign-in for everyone with an AFISAB account. After you sign in, we take you to the right place for your role.</p>
        <ul class="investor-who">
          <li><strong>Investors</strong> view investments, profit payments, maturity dates and account documents.</li>
          <li><strong>AFISAB staff</strong> access the administration area.</li>
        </ul>
      </div>
      <section class="investor-card" aria-labelledby="signin-title">
        <div class="investor-lock"><svg class="icon icon--lock" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg></div>
        <h2 id="signin-title">Investor &amp; staff sign in</h2>
        @if(session('status'))<p class="investor-status" role="status">{{ session('status') }}</p>@endif
        <form method="POST" action="{{ route('login') }}" class="investor-fields">@csrf
          <x-site-field name="email" label="Email address" type="email" required autocomplete="username" />
          <x-site-field name="password" label="Password" type="password" required autocomplete="current-password" />
          <label class="investor-remember"><input type="checkbox" name="remember" value="1"> Remember me</label>
          <button class="button button--forest" type="submit">Sign in</button>
        </form>
        <div class="investor-links">
          <a class="text-link" href="{{ route('password.request') }}">Forgot password?</a>
          <a class="text-link" href="{{ route('register') }}">Create an investor account</a>
        </div>
      </section>
    </div>
  </main>
</x-site-layout>
