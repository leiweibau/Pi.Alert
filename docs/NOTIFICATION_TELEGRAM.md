# Telegram notifications

Pi.Alert sends Telegram notifications directly through the
[Telegram Bot API](https://core.telegram.org/bots/api#sendmessage). The bundled
Shoutrrr binaries are retained as an optional setup helper for discovering the
chat IDs that can receive messages. Pi.Alert does not use Shoutrrr to send
notifications.

## Configuration

Telegram uses these settings in `config/pialert.conf`:

```python
REPORT_TELEGRAM = True
REPORT_TELEGRAM_WEBMON = True
TELEGRAM_BOT_TOKEN = '<YOUR BOT TOKEN>'
TELEGRAM_CHAT_IDS = ['<CHAT ID>']
```

`TELEGRAM_CHAT_IDS` is a list. It can contain a private chat ID, a negative
group chat ID, a channel username such as `@example_channel`, or several of
these destinations:

```python
TELEGRAM_CHAT_IDS = ['123456789', '-1001234567890', '@example_channel']
```

The bot token is a secret. Do not paste a real token into logs, screenshots,
issues, or test files. The Pi.Alert configuration editor masks it when the
configuration is displayed.

## Create the bot

Create a bot with
[BotFather](https://core.telegram.org/bots/features#creating-a-new-bot) and
keep the issued bot token private. Then send a message such as `/start` to the
bot. For a group, add the bot to the group and send a command addressed to it.

## Discover a chat ID in the browser

The chat ID can be obtained directly from Telegram without Shoutrrr:

1. Send a new message such as `/start` to the bot. For a group, send a bot
   command in that group. For a channel, add the bot with the required access
   and create a channel post.
2. Replace `<YOUR_BOT_TOKEN>` in this address with the BotFather token and open
   it in a browser:

   ```text
   https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates
   ```

3. In the JSON response, locate the appropriate value:

   - private chat or group: `result[].message.chat.id`
   - channel: `result[].channel_post.chat.id`

   A result can look like this:

   ```json
   {
     "ok": true,
     "result": [
       {
         "message": {
           "chat": {
             "id": -1001234567890,
             "title": "Example group",
             "type": "supergroup"
           }
         }
       }
     ]
   }
   ```

4. Copy only the value from `chat.id` into `TELEGRAM_CHAT_IDS`:

   ```python
   TELEGRAM_CHAT_IDS = ['-1001234567890']
   ```

5. Store the BotFather token separately in `TELEGRAM_BOT_TOKEN`, enable the
   required Telegram report options, and run the notification test from the
   Pi.Alert maintenance page.

The browser address contains the complete bot token and can remain in browser
history, synchronization data, proxy logs, or screenshots. Use a private
window, close it afterwards, and clear the relevant history entry. Do not use
this method on an untrusted or shared computer. The command-line method that
prompts for the token without placing it in browser history is safer.

If `result` is empty, send another message and reload the address. In groups
with bot privacy enabled, use a bot command rather than an ordinary message.
Telegram's `getUpdates` method does not work while an outgoing webhook is
configured for the bot. See the official
[`getUpdates` documentation](https://core.telegram.org/bots/api#getupdates).

For a public channel, Pi.Alert also accepts its username directly, for example
`TELEGRAM_CHAT_IDS = ['@example_channel']`. A separate Telegram user ID setting
is not needed for a private bot conversation.

## Optional discovery with Shoutrrr

The bundled Shoutrrr generator remains available as an alternative interactive
helper:

1. Select `back/shoutrrr/arm64`, `back/shoutrrr/armhf`, or
   `back/shoutrrr/x86` for the installed architecture.
2. Run `./shoutrrr generate telegram` manually.
3. Enter the BotFather token and select the desired chats.
4. Copy the token and the comma-separated `chats` values from the generated URL
   into `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_IDS`.

Pi.Alert does not start the Shoutrrr binary to deliver notifications.

## Compatibility with existing installations

The former setting remains temporarily supported:

```python
TELEGRAM_BOT_TOKEN_URL = 'telegram://<TOKEN>@telegram?chats=<CHAT_IDS>&preview=No'
```

When both new settings are empty, Pi.Alert parses a valid legacy URL as data
and sends the message directly through the Telegram Bot API. It does not start
the Shoutrrr binary. Complete direct settings take precedence over the legacy
URL. A partial direct configuration is rejected instead of silently falling
back.

Migrate the token and chat IDs to the new settings when convenient. The legacy
URL contains the bot token and must be protected like any other secret.

## Delivery behavior

- Messages are sent as plain text without Telegram HTML or Markdown parsing.
- Messages longer than Telegram's 4096-character limit are split into ordered
  parts.
- Link previews remain disabled by default. Valid legacy `preview` and
  `notification` flags are preserved during the compatibility period.
- Redirects are disabled and TLS certificate verification remains enabled.
- Errors do not include the bot token or token-bearing API URL.
- Each configured chat receives its own API request.

[Back](https://github.com/leiweibau/Pi.Alert#back)
