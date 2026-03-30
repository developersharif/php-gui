# PHP GUI

## Build native desktop apps in pure PHP

> Create cross-platform graphical interfaces using pure PHP &mdash; zero compiled extensions, zero massive browser engines.

<p style="font-size: 0.85em; opacity: 0.7; margin-top: -12px; margin-bottom: 24px;">✨ Inspired by Python's Tkinter, built for modern PHP.</p>
<div class="hero-mock-window" style="margin: 32px auto 40px; box-shadow: 0 35px 60px -15px rgba(0, 0, 0, 0.6); max-width: 780px; text-align: left; z-index: 10; position: relative;">
  <div class="mock-titlebar">
    <div class="mock-controls"><span></span><span></span><span></span></div>
    <div class="mock-title">main.php - PHP GUI</div>
  </div>
  <div class="mock-content">
    <div class="mock-editor">
      <div class="mock-line"><span>1</span> <span class="kw">use</span> <span class="cl">PhpGui\Application</span>;</div>
      <div class="mock-line"><span>2</span> <span class="kw">use</span> <span class="cl">PhpGui\Widget\Window</span>;</div>
      <div class="mock-line"><span>3</span> <span class="kw">use</span> <span class="cl">PhpGui\Widget\Button</span>;</div>
      <div class="mock-line"><span>4</span> </div>
      <div class="mock-line"><span>5</span> <span class="va">$app</span> = <span class="kw">new</span> <span class="cl">Application</span>();</div>
      <div class="mock-line"><span>6</span> <span class="va">$window</span> = <span class="kw">new</span> <span class="cl">Window</span>([<span class="st">'title'</span> => <span class="st">'Native UI'</span>]);</div>
      <div class="mock-line"><span>7</span> </div>
      <div class="mock-line"><span>8</span> <span class="va">$btn</span> = <span class="kw">new</span> <span class="cl">Button</span>(<span class="va">$window</span>, [<span class="st">'text'</span> => <span class="st">'Click Me!'</span>]);</div>
      <div class="mock-line"><span>9</span> <span class="va">$btn</span>-><span class="fn">pack</span>();</div>
      <div class="mock-line"><span>10</span> </div>
      <div class="mock-line"><span>11</span> <span class="va">$app</span>-><span class="fn">run</span>();</div>
    </div>
    <div class="mock-gui-preview">
      <div class="preview-titlebar">Native UI</div>
      <div class="preview-body">
        <button class="preview-btn">Click Me!</button>
      </div>
    </div>
  </div>
</div>


[Get Started](getting-started.md)
[View on GitHub](https://github.com/developersharif/php-gui)
