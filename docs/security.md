# Security — Tcl Quoting

php-gui builds Tcl commands as strings before handing them to the Tk interpreter via FFI. Any user-supplied data that lands in those commands has to be quoted so it can't be re-interpreted as Tcl code. Since v1.9 every widget enforces that quoting consistently.

If you only ever pass *trusted* values (literals you wrote yourself in PHP source) to widget options you can ignore this page. If you display **any data that originates outside your code** — DB rows, file contents, network responses, command-line arguments, user input — read on.

---

## What gets quoted

`AbstractWidget::tclQuote(string $value): string` is the canonical helper. It wraps the value in `"…"` and escapes the four characters Tcl interprets inside double quotes:

| Character | Why it's dangerous unquoted | Replaced with |
|-----------|------------------------------|---------------|
| `\` | Continues escapes into the rest of the string | `\\` |
| `$` | Triggers Tcl variable substitution (`$varname`) | `\$` |
| `[` | Triggers Tcl command substitution (`[exec rm -rf /]`) | `\[` |
| `"` | Closes the quoted word and lets the next characters be parsed as code | `\"` |

Brace-quoting (`{…}`) was rejected because a value ending in a backslash would produce `{value\}`, where `\}` reads as a literal `}` and never closes the group — Tcl raises *"unbalanced braces"* and the call crashes.

Every widget that interpolates a user-provided string runs it through `tclQuote()` (or, where possible, sets a Tcl variable directly via `Tcl_SetVar2`, which bypasses string parsing entirely).

---

## What this means in practice

Without quoting, a label whose text is `Hello"; destroy .; "` would close out of the `\"…\"` quoting in the generated Tcl command and execute `destroy .`, ripping the application's root window out from under the user. With quoting, the literal characters appear in the label and Tk renders them as text.

```php
$label = new Label($window->getId(), [
    'text' => 'Hello"; destroy .; "',
]);
// Renders the literal string "Hello\"; destroy .; \"" — the window stays alive.
```

The same protection applies to:

- All Label/Button/Checkbutton/Input/Entry/Combobox/Menu/Menubutton/Message/TopLevel/Window/Canvas option values and labels.
- `Label::setText / setFont / setForeground / setBackground / setState`
- `Button::__construct`'s `text` and option keys
- `Input::setValue / getValue` (routed through `Tcl_SetVar`/`Tcl_GetVar`, no string interpolation at all)
- `Menu::addCommand` / `addSubmenu` labels
- `Canvas::drawText` text and option values
- `TopLevel::messageBox / chooseColor / getOpenFile`-family static dialogs
- `Window` / `TopLevel` titles

---

## Custom widgets and direct evalTcl

If you extend `AbstractWidget` to wrap a Tk widget yourself, call `tclQuote()` on every interpolated user value:

```php
$this->tcl->evalTcl(
    "mywidget {$this->tclPath} -label " . self::tclQuote($userLabel)
);
```

If you bypass the widget API and call `ProcessTCL::evalTcl()` directly, the same rule applies — quote anything that came from outside your code. Better still, pass values via `setVar()` and reference them as `$varname` in your command, which avoids string parsing of the value entirely.

---

## What still gets validated by Tk

Quoting protects against **arbitrary code execution**. It does not turn invalid values into valid ones — `bg => 'not-a-real-color"; destroy .; "'` is now safely rejected by Tk with an *"invalid color name"* error rather than executing the destroy. If you accept option values from users you should still validate them (or wrap the call in `try { … } catch (\RuntimeException)`).

---

## Regression test

`tests/widgets_test/TclInjectionTest.php` is the regression suite. It feeds escape-attempt payloads through `tclQuote()`, every widget setter, `Combobox` value lists, and `Canvas::drawText`, and asserts the parent window still exists and the value round-trips literally back through `cget`. Run it as part of any change to widget construction:

```bash
php tests/widgets_test/TclInjectionTest.php
```
