@extends('platform.layouts.app')

@section('title', 'Platform Health')
@section('page-heading', 'Platform Health')

@section('content')
  <div class="card">
    <div class="card-body">
      <p class="f-light">
        Spec §31 lists several more console sections that are honestly not built yet, because
        each depends on a module that doesn't exist in this codebase today:
      </p>
      <ul style="line-style:disc;padding-left:18px">
        <li><strong>System health / Usage</strong> — needs product analytics (§36) or Insights (§16), neither built.</li>
        <li><strong>Failed jobs</strong> — needs a persistent queue worker; §33's own notes record that shared/cPanel hosting can't run one yet (<code>D-011</code>).</li>
        <li><strong>Notification delivery logs</strong> — needs the Notifications module (§13), which is built but unregistered and blocked on <code>D-011</code>.</li>
        <li><strong>Integration status</strong> — needs the Integrations module (§30), not started.</li>
        <li><strong>AI/voice usage</strong> — needs the AI Voice Agent module (§19), not started.</li>
        <li><strong>Billing events</strong> (beyond a tenant's own current status) — would need a platform-wide event feed Billing doesn't expose yet.</li>
        <li><strong>Feature flags</strong> — this product gates by plan entitlement (§25), not by flag; there is no flag concept to show.</li>
        <li><strong>Website/content templates</strong> — needs the Website module (§14), not started.</li>
      </ul>
      <p class="f-light mb-0">
        What already exists — tenant search, subscription/plan status, feature entitlements,
        account-level user management, and the audit log — lives on the other screens in this
        console.
      </p>
    </div>
  </div>
@endsection
