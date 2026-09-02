<?php

namespace App\Http\Controllers;

use App\Drive\Contracts\DriveAuthenticator;
use App\Drive\DriveAccount;
use App\Drive\Exceptions\DriveException;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Where Google sends the user back after the consent screen.
 *
 * Every path ends on the settings screen with a message, because that is the
 * only place the outcome means anything.
 */
final class DriveCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        DriveAuthenticator $authenticator,
        AppSettings $settings,
        DriveAccount $account,
    ): RedirectResponse {
        $expected = $request->session()->pull(ConnectDriveController::STATE_KEY);
        $state = $request->query('state');

        // A missing or mismatched state means this callback did not originate
        // from a redirect this session started.
        abort_if(! is_string($expected) || $expected === '' || $state !== $expected, 403, (string) __('ui.drive.invalid_state'));

        // The user pressed "Cancel" on the consent screen, which is not an
        // error worth a stack trace.
        if ($request->query('error') !== null) {
            return $this->back('drive_error', (string) __('ui.drive.cancelled'));
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $this->back('drive_error', (string) __('ui.drive.cancelled'));
        }

        try {
            $credentials = $authenticator->exchange($code);
        } catch (DriveException $exception) {
            return $this->back('drive_error', $exception->getMessage());
        }

        $settings->setDriveRefreshToken($credentials->refreshToken);

        // Asked for only now that the token is stored, because that is what
        // authorizes the call. Not knowing the address is not a failed
        // connection: the integration works either way.
        $email = $account->email();

        return $email === null
            ? $this->back('drive_status', (string) __('ui.drive.connected_unknown'))
            : $this->back('drive_status', (string) __('ui.drive.connected', ['email' => $email]));
    }

    private function back(string $key, string $message): RedirectResponse
    {
        return redirect()->route('settings.edit')->with($key, $message);
    }
}
