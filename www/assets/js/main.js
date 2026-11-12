const scroller = document.querySelector('[data-project-scroll]');
const original = scroller?.querySelector('.project-sequence');

if (original && original.children.length > 1) {
  // CSS handles snapping. Group recycling follows the native-scroll approach at:
  // https://github.com/woshibide/wshbd/blob/main/src/scripts/selected-scroll.js
  const host = original.parentElement;
  const template = original.cloneNode(true);
  const projectCount = original.children.length;
  let frameHeight = original.firstElementChild.getBoundingClientRect().height;
  let cycleHeight = frameHeight * projectCount;
  const cycleCount = Math.max(8, Math.floor(8_000_000 / cycleHeight));
  const origin = Math.floor(cycleCount / 2);
  const groups = new Map([[origin, original]]);
  let logicalPosition = origin * projectCount;
  let touchGroup = null;
  let touching = false;
  let animationFrame = 0;
  let resizeFrame = 0;
  let resizing = false;
  let idleTimer;

  const makeSpace = () => {
    const space = document.createElement('div');
    space.className = 'project-scroll-space';
    space.setAttribute('aria-hidden', 'true');
    return space;
  };
  const leadingSpace = makeSpace();
  const trailingSpace = makeSpace();
  host.prepend(leadingSpace);
  host.append(trailingSpace);

  const sizeSpaces = () => {
    const indexes = [...groups.keys()];
    leadingSpace.style.height = `${Math.min(...indexes) * cycleHeight}px`;
    trailingSpace.style.height = `${(cycleCount - Math.max(...indexes) - 1) * cycleHeight}px`;
  };

  const createGroup = (index) => {
    const group = template.cloneNode(true);
    group.querySelectorAll('[id]').forEach((element) => element.removeAttribute('id'));
    group.querySelectorAll('img').forEach((image) => {
      image.loading = 'lazy';
      image.decoding = 'async';
    });
    const nextIndex = [...groups.keys()].filter((key) => key > index).sort((a, b) => a - b)[0];
    host.insertBefore(group, groups.get(nextIndex) ?? trailingSpace);
    groups.set(index, group);
  };

  const populate = () => {
    if (resizing) return;
    // ResizeObserver restores the logical position once CSS has changed frame height.
    const measuredHeight = groups.values().next().value.firstElementChild.getBoundingClientRect().height;
    if (measuredHeight !== frameHeight) return;

    const position = scroller.scrollTop;
    logicalPosition = position / frameHeight;
    const currentCycle = Math.max(0, Math.min(cycleCount - 1, Math.floor(position / cycleHeight)));
    const heldCycle = touchGroup
      ? [...groups].find(([, group]) => group === touchGroup)?.[0] ?? currentCycle
      : currentCycle;
    const first = Math.max(0, Math.min(currentCycle - 1, heldCycle));
    const last = Math.min(cycleCount - 1, Math.max(currentCycle + 1, heldCycle));

    // The spacer exchange keeps every surviving project's document coordinates fixed.
    for (let index = first; index <= last; index++) {
      if (!groups.has(index)) createGroup(index);
    }
    for (const [index, group] of groups) {
      if (index >= first && index <= last) continue;
      if (group.contains(document.activeElement)) scroller.focus({ preventScroll: true });
      group.remove();
      groups.delete(index);
    }
    sizeSpaces();

    // Keep one readable cycle in the tab order, while retaining a focused group's context.
    for (const [index, group] of groups) {
      const accessible = index === currentCycle || group.contains(document.activeElement);
      group.toggleAttribute('aria-hidden', !accessible);
      if (!accessible) group.setAttribute('aria-hidden', 'true');
      group.querySelectorAll('a[href]').forEach((link) => {
        if (accessible) link.removeAttribute('tabindex');
        else link.tabIndex = -1;
      });
    }
  };

  // Initial placement is the only scroll write in an ordinary browsing session.
  sizeSpaces();
  scroller.scrollTo({ top: origin * cycleHeight, behavior: 'instant' });
  populate();

  const recoverRange = () => {
    if (touching || resizing) return;
    const cycle = Math.floor(scroller.scrollTop / cycleHeight);
    if (cycle > 2 && cycle < cycleCount - 3) return;
    const shift = origin - cycle;
    const position = scroller.scrollTop + shift * cycleHeight;
    const entries = [...groups];
    groups.clear();
    entries.forEach(([index, group]) => groups.set(index + shift, group));
    logicalPosition += shift * projectCount;
    sizeSpaces();
    scroller.scrollTo({ top: position, behavior: 'instant' });
    populate();
  };

  const scheduleRecovery = () => {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(recoverRange, 250);
  };

  scroller.addEventListener('scroll', () => {
    if (!animationFrame) {
      animationFrame = requestAnimationFrame(() => {
        animationFrame = 0;
        populate();
      });
    }
    scheduleRecovery();
  }, { passive: true });

  scroller.addEventListener('touchstart', (event) => {
    touching = true;
    touchGroup = event.target.closest('.project-sequence');
    clearTimeout(idleTimer);
  }, { passive: true });

  const finishTouch = (event) => {
    if (event.touches.length) return;
    touching = false;
    touchGroup = null;
    populate();
    scheduleRecovery();
  };
  scroller.addEventListener('touchend', finishTouch, { passive: true });
  scroller.addEventListener('touchcancel', finishTouch, { passive: true });

  scroller.addEventListener('focusin', () => populate());

  new ResizeObserver(() => {
    const height = groups.values().next().value.firstElementChild.getBoundingClientRect().height;
    if (!height || height === frameHeight) return;
    const position = logicalPosition;
    resizing = true;
    clearTimeout(idleTimer);
    cancelAnimationFrame(resizeFrame);
    scroller.style.scrollSnapType = 'none';
    frameHeight = height;
    cycleHeight = height * projectCount;
    sizeSpaces();
    scroller.scrollTo({ top: position * height, behavior: 'instant' });
    // Let the compositor discard the old snap target before restoring CSS snapping.
    resizeFrame = requestAnimationFrame(() => {
      scroller.scrollTo({ top: position * frameHeight, behavior: 'instant' });
      scroller.style.removeProperty('scroll-snap-type');
      resizing = false;
      populate();
    });
  }).observe(scroller);
}
