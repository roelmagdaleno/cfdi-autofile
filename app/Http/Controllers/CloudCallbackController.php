<?php

namespace App\Http\Controllers;

use App\Cloud\CloudAccount;
use App\Cloud\Contracts\CloudOAuth;
use App\Cloud\Exceptions\CloudException;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where the provider sends the user back after the consent screen.
 *
 * Every path ends on the settings screen with a message, because that is the
 * only place the outcome means anything.
 */
final class CloudCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        CloudOAuth $authenticator,
        AppSettings $settings,
        CloudAccount $account,
    ): RedirectResponse {
        $provider = $settings->cloudProvider();
        $expected = $request->session()->pull(ConnectCloudController::STATE_KEY);
        $state = $request->query('state');

        // A missing or mismatched state means this callback did not originate
        // from a redirect this session started.
        abort_if(! is_string($expected) || $expected === '' || $state !== $expected, 403, (string) __('ui.cloud.invalid_state'));

        // The user pressed "Cancel" on the consent screen, which is not an
        // error worth a stack trace.
        if ($request->query('error') !== null) {
            return $this->back('cloud_error', (string) __('ui.cloud.cancelled', ['provider' => $provider->label()]));
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->back('cloud_error', (string) __('ui.cloud.cancelled', ['provider' => $provider->label()]));
        }

        try {
            $credentials = $authenticator->exchange($code);
        } catch (CloudException $exception) {
            return $this->back('cloud_error', $exception->getMessage());
        }

        $settings->setCredential($provider, $credentials->refreshToken);

        // Asked for only now that the token is stored, because that is what
        // authorizes the call. Not knowing the account is not a failed
        // connection: the integration works either way.
        $label = $account->label();

        return $label === null
            ? $this->back('cloud_status', (string) __('ui.cloud.connected_unknown', ['provider' => $provider->label()]))
            : $this->back('cloud_status', (string) __('ui.cloud.connected', ['account' => $label]));
    }

    private function back(string $key, string $message): RedirectResponse
    {
        return redirect()->route('settings.edit')->with($key, $message);
    }
}
