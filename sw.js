self.addEventListener('fetch', (event) => {
  // This can be empty, but it must exist to satisfy PWA requirements
});

let deferredPrompt;
const installBtn = document.getElementById('install-btn'); // Your "Install App" button

window.addEventListener('beforeinstallprompt', (e) => {
  // Prevent Chrome 67 and earlier from automatically showing the prompt
  e.preventDefault();
  // Stash the event so it can be triggered later.
  deferredPrompt = e;
  // Show your custom "Install H&S App" banner here
  showMyCustomBanner(); 
});

installBtn.addEventListener('click', async () => {
  if (deferredPrompt) {
    // Show the browser install prompt
    deferredPrompt.prompt();
    // Wait for the user to respond to the prompt
    const { outcome } = await deferredPrompt.userChoice;
    console.log(`User response to the install prompt: ${outcome}`);
    // We've used the prompt, and can't use it again
    deferredPrompt = null;
    hideMyCustomBanner();
  }
});
