@component('mail::message')
# Hello!

**{{ $trainerName }}** has invited you to join their wellness program on **BioVue**. 
To start your journey and connect with your trainer, please click the button below:

@component('mail::button', ['url' => $url])
Accept Invitation
@endcomponent

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
The {{ config('app.name') }} Team
@endcomponent