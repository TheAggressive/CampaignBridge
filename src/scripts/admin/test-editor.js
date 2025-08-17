/**
 * Simple test script to verify JavaScript loading works
 */

console.log('🚀 TEST SCRIPT LOADED - External JavaScript is working!');

document.addEventListener('DOMContentLoaded', function () {
  const container = document.getElementById('cb-standalone-editor');
  if (container) {
    container.innerHTML =
      '<div style="background: green; color: white; padding: 20px; font-size: 18px; font-weight: bold;">✅ EXTERNAL JAVASCRIPT IS WORKING! Now we can debug the main editor.</div>';
    console.log('Test script found container and updated it');
  } else {
    console.error('Test script: Container not found');
  }
});
