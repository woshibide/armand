const { test, expect } = require('@playwright/test');

const projectNames = ['Project one', 'Project two', 'Project three'];

test.afterEach(async ({ page }, testInfo) => {
  if (testInfo.status !== testInfo.expectedStatus) {
    const state = await page.evaluate(() => ({
      scrollTop: document.querySelector('[data-project-scroll]')?.scrollTop,
      frames: [...document.querySelectorAll('.project-frame')].map((element) => ({
        title: element.querySelector('h2').textContent,
        top: element.getBoundingClientRect().top,
      })),
    }));
    await testInfo.attach('scroll-state', { body: JSON.stringify(state, null, 2), contentType: 'application/json' });
  }
});

async function expectProject(page, name) {
  await expect.poll(() => page.locator('.project-frame').evaluateAll((frames) => {
    const current = frames.find((frame) => Math.abs(frame.getBoundingClientRect().top) < 1);
    return current?.querySelector('h2').textContent;
  })).toBe(name);
}

async function wheel(page, direction = 1) {
  const { width, height } = page.viewportSize();
  await page.mouse.move(width / 2, height / 2);
  await page.mouse.wheel(0, direction * height * 0.8);
}

test('frames fill the viewport, use the local font and have no footer or Work link', async ({ page }) => {
  const errors = [];
  const externalRequests = [];
  page.on('pageerror', (error) => errors.push(error.message));
  page.on('request', (request) => {
    if (new URL(request.url()).hostname !== '127.0.0.1') externalRequests.push(request.url());
  });
  await page.goto('/');
  await page.evaluate(() => document.fonts.ready);
  await expectProject(page, projectNames[0]);
  await expect(page.locator('footer')).toHaveCount(0);
  await expect(page.getByRole('link', { name: 'Work', exact: true })).toHaveCount(0);
  await expect(page.getByRole('navigation', { name: 'Main navigation' }).getByRole('link')).toHaveText(['About']);
  await expect(page.getByRole('link', { name: /^Project (one|two|three)$/ })).toHaveCount(3);
  await expect(page.locator('.project-sequence')).toHaveCount(3);
  const dimensions = await page.locator('.project-frame').first().evaluate((frame) => ({
    width: frame.getBoundingClientRect().width,
    height: frame.getBoundingClientRect().height,
    viewportWidth: innerWidth,
    viewportHeight: innerHeight,
    documentWidth: document.documentElement.scrollWidth,
    loadedFont: document.fonts.check('20px "Inter Tight"'),
    weight: getComputedStyle(frame).fontWeight,
  }));
  expect(dimensions.width).toBe(dimensions.viewportWidth);
  expect(dimensions.height).toBe(dimensions.viewportHeight);
  expect(dimensions.documentWidth).toBe(dimensions.viewportWidth);
  expect(dimensions.loadedFont).toBe(true);
  expect(dimensions.weight).toBe('400');
  expect(externalRequests).toEqual([]);
  expect(errors).toEqual([]);
});

async function sampleNativeScroll(page) {
  await page.evaluate(() => {
    window.scrollWrites = [];
    window.scrollSamples = [];
    window.sampleScrolling = true;
    const scroller = document.querySelector('[data-project-scroll]');
    const nativeScrollTo = scroller.scrollTo.bind(scroller);
    scroller.scrollTo = (...args) => {
      window.scrollWrites.push(args);
      return nativeScrollTo(...args);
    };
    const sample = () => {
      if (!window.sampleScrolling) return;
      const frames = [...document.querySelectorAll('.project-frame')];
      const covered = frames.reduce((sum, frame) => {
        const box = frame.getBoundingClientRect();
        return sum + Math.max(0, Math.min(box.bottom, innerHeight) - Math.max(box.top, 0));
      }, 0);
      window.scrollSamples.push({ covered, count: frames.length });
      requestAnimationFrame(sample);
    };
    requestAnimationFrame(sample);
  });
}

async function expectNativeScroll(page) {
  const state = await page.evaluate(() => {
    window.sampleScrolling = false;
    return {
      writes: window.scrollWrites.length,
      covered: window.scrollSamples.every((sample) => sample.covered >= innerHeight - 1),
      bounded: window.scrollSamples.every((sample) => sample.count <= 9),
    };
  });
  expect(state).toEqual({ writes: 0, covered: true, bounded: true });
}

test('wheel scrolling loops in both directions without repositioning or blank frames', async ({ page }) => {
  await page.goto('/');
  await expectProject(page, projectNames[0]);
  await sampleNativeScroll(page);
  for (const direction of [1, -1]) {
    for (let step = 1; step <= 9; step++) {
      await wheel(page, direction);
      await expectProject(page, projectNames[((step * direction) % 3 + 3) % 3]);
    }
  }
  await expectNativeScroll(page);
  await expect(page.getByRole('link', { name: /^Project (one|two|three)$/ })).toHaveCount(3);
});

test('continuous wheel momentum crosses many cycles without scroll writes', async ({ page }) => {
  await page.goto('/');
  await sampleNativeScroll(page);
  const { width, height } = page.viewportSize();
  await page.mouse.move(width / 2, height / 2);
  for (const direction of [1, -1]) {
    for (let step = 0; step < 30; step++) {
      await page.mouse.wheel(0, direction * height);
      await page.waitForTimeout(65);
    }
    await expect.poll(() => page.locator('.project-frame').evaluateAll((frames) =>
      frames.some((frame) => Math.abs(frame.getBoundingClientRect().top) < 1),
    )).toBe(true);
  }
  await expectNativeScroll(page);
});

test('idle recovery restores the distant scroll range without changing the project', async ({ page }) => {
  await page.goto('/');
  await sampleNativeScroll(page);
  await page.evaluate(() => {
    const scroller = document.querySelector('[data-project-scroll]');
    scroller.style.scrollSnapType = 'none';
    scroller.scrollTop = scroller.clientHeight * 4;
  });
  await expectProject(page, projectNames[1]);
  await page.locator('[data-project-scroll]').evaluate((scroller) => scroller.style.removeProperty('scroll-snap-type'));
  await expect.poll(() => page.locator('[data-project-scroll]').evaluate((scroller) => scroller.scrollTop)).toBeGreaterThan(100000);
  await expectProject(page, projectNames[1]);
  expect(await page.evaluate(() => window.scrollWrites.length)).toBe(1);
});

test('a large wheel gesture can pass intermediate projects', async ({ page }) => {
  await page.goto('/');
  const { width, height } = page.viewportSize();
  await page.mouse.move(width / 2, height / 2);
  await page.mouse.wheel(0, height * 2);
  await expectProject(page, projectNames[2]);
  await page.mouse.wheel(0, -height * 2);
  await expectProject(page, projectNames[0]);
});

test('skip link and native page keys navigate without custom keyboard handlers', async ({ page }) => {
  await page.goto('/');
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Skip to content' })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#main')).toBeFocused();
  await page.keyboard.press('PageDown');
  await expectProject(page, projectNames[1]);
  await page.keyboard.press('PageDown');
  await expectProject(page, projectNames[2]);
  await page.keyboard.press('PageUp');
  await expectProject(page, projectNames[1]);
  await page.keyboard.press('Home');
  await expectProject(page, projectNames[0]);
  await expect(page.locator('#main')).toBeFocused();
});

test('resize preserves the snapped project and subsequent native scrolling', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto('/');
  await wheel(page);
  await expectProject(page, projectNames[1]);
  await page.setViewportSize({ width: 844, height: 390 });
  await expectProject(page, projectNames[1]);
  expect(await page.locator('[data-project-scroll]').evaluate((element) => getComputedStyle(element).scrollBehavior)).toBe('auto');
  await wheel(page);
  await expectProject(page, projectNames[2]);
});

test('project and about links work, with exact copy and a dated inner-page footer', async ({ page }) => {
  await page.goto('/');
  await wheel(page);
  await expectProject(page, projectNames[1]);
  await page.getByRole('link', { name: projectNames[1], exact: true }).click();
  await expect(page).toHaveURL(/\/projects\/project-two\/?$/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(projectNames[1]);
  await expect(page.locator('footer')).toHaveText(/Website was last updated \d{2}\.\d{2}\.\d{4}, Armand Demulder forever/);
  await page.getByRole('link', { name: 'About', exact: true }).click();
  await expect(page).toHaveURL(/\/about\/?$/);
  await expect(page.locator('.about-statement')).toHaveText('Armand is a jolly french man with a big heart. Bissous bissous');
  await expect(page.locator('footer time')).toHaveAttribute('datetime', /^\d{4}-\d{2}-\d{2}T/);
  await expect(page.getByRole('link', { name: 'Work', exact: true })).toHaveCount(0);
  expect(await page.evaluate(() => getComputedStyle(document.documentElement).scrollSnapType)).toBe('none');
  await page.getByRole('link', { name: 'Armand Demulder', exact: true }).click();
  await expectProject(page, projectNames[0]);
});

test('touch swipe advances a full frame without scroll writes', async ({ page, context, isMobile }) => {
  test.skip(!isMobile, 'Touch input is covered by the mobile project.');
  await page.goto('/');
  await expectProject(page, projectNames[0]);
  await sampleNativeScroll(page);
  const { width, height } = page.viewportSize();
  const session = await context.newCDPSession(page);
  const point = (y) => [{ x: width / 2, y }];
  await session.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: point(height * 0.8) });
  for (let step = 1; step <= 10; step++) {
    await session.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: point(height * (0.8 - step * 0.06)) });
    await page.waitForTimeout(20);
  }
  await session.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
  await expectProject(page, projectNames[1]);
  await expectNativeScroll(page);
});

test('touch momentum crosses project cycles without repositioning or blank space', async ({ page, context, isMobile }) => {
  test.skip(!isMobile, 'Touch momentum is covered by the mobile project.');
  await page.goto('/');
  await sampleNativeScroll(page);
  const { width, height } = page.viewportSize();
  const session = await context.newCDPSession(page);
  await session.send('Input.synthesizeScrollGesture', {
    x: width / 2,
    y: height / 2,
    yDistance: -height * 4,
    speed: height * 8,
    gestureSourceType: 'touch',
  });
  await expect.poll(() => page.locator('.project-frame').evaluateAll((frames) =>
    frames.some((frame) => Math.abs(frame.getBoundingClientRect().top) < 1),
  )).toBe(true);
  await expect(page.locator('.project-sequence')).toHaveCount(3);
  const state = await page.evaluate(() => {
    window.sampleScrolling = false;
    return {
      writes: window.scrollWrites.length,
      covered: window.scrollSamples.every((sample) => sample.covered >= innerHeight - 1),
    };
  });
  expect(state).toEqual({ writes: 0, covered: true });
});

test.describe('without JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('the same CSS snapping and project navigation work', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('footer')).toHaveCount(0);
    await expect(page.locator('.project-frame')).toHaveCount(3);
    await wheel(page);
    await expectProject(page, projectNames[1]);
    await page.getByRole('link', { name: projectNames[1], exact: true }).click();
    await expect(page.getByRole('heading', { level: 1 })).toHaveText(projectNames[1]);
  });
});
