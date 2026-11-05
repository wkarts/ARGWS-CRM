'use strict';

// Viewport emulation can finish before the delivered navigation resize handler.
// Wait for its state, never for the overflow assertion itself to become true.
async function measureNativeMobileLayout(page, timeout = 5000) {
  await page.waitForFunction(() => innerWidth < 769 && document.body.classList.contains('page-small'), null, {timeout});
  await page.evaluate(async () => {
    await document.fonts.ready;
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  });
  return page.evaluate(() => ({
    width: innerWidth,
    scrollWidth: document.documentElement.scrollWidth,
    bodyClasses: document.body.className,
    overflowing: [...document.querySelectorAll('body *')].map(element => {
      const rect = element.getBoundingClientRect();
      return {tag: element.tagName, id: element.id, classes: element.getAttribute('class'), right: rect.right, width: rect.width, height: rect.height};
    }).filter(rect => rect.width > 0 && rect.height > 0 && rect.right > innerWidth + 1).slice(0, 20),
  }));
}

module.exports = {measureNativeMobileLayout};
