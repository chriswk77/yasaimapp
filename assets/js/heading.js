/**
 * Where one looks through the phone, in clockwise degrees from north.
 *
 * OUT OF map.js ON 29.09.2026, when the vector-map trial page needed the
 * same answer: one reckoning for both maps, not a copy that drifts. How it
 * was found — the flat-table detour, the quarter turn held across — stands
 * at the heading cone in map.js; the reckoning itself is here, checked
 * against poses built by hand (flat, tilted, upright, across both ways).
 */

/**
 * The phone's axes in the earth's frame (x east, y north, z up) from the
 * three angles of the specification (Z-X'-Y''); forward is the top of the
 * screen plus the phone's back (-z), laid flat onto the ground. Null where
 * the two cancel out — a phone held over the head, screen down.
 */
export function forwardHeading(alpha, beta, gamma, screenAngle) {
    const r = Math.PI / 180;
    const cA = Math.cos(alpha * r), sA = Math.sin(alpha * r);
    const cB = Math.cos(beta * r),  sB = Math.sin(beta * r);
    const cG = Math.cos(gamma * r), sG = Math.sin(gamma * r);
    // Only the east and north parts are needed — up is what gets dropped.
    const x = [cA * cG - sA * sB * sG, sA * cG + cA * sB * sG];
    const y = [-sA * cB, cA * cB];
    const z = [cA * sG + sA * sB * cG, sA * sG - cA * sB * cG];
    // The top of the screen, in the phone's own axes: y upright, x with the
    // top edge to the left (90), -x with it to the right (270).
    const su = Math.sin(screenAngle * r), cu = Math.cos(screenAngle * r);
    const east  = su * x[0] + cu * y[0] - z[0];
    const north = su * x[1] + cu * y[1] - z[1];
    if (Math.hypot(east, north) < 1e-6) return null;
    return (Math.atan2(east, north) / r + 360) % 360;
}

/** Where one looks, from one orientation event — or null for no compass. */
export function headingOf(e) {
    if (Number.isFinite(e.webkitCompassHeading)) return e.webkitCompassHeading;
    // Only a north-referenced reading is a compass; a relative one says
    // where the phone turned since the page opened.
    if (!e.absolute || !Number.isFinite(e.alpha)) return null;
    const screenAngle = screen.orientation?.angle ?? window.orientation ?? 0;
    return forwardHeading(e.alpha, e.beta ?? 0, e.gamma ?? 0, screenAngle);
}
