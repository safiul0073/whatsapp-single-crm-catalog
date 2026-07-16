import { initCharts } from "./charts.js";

/**
 * Toggles the application theme between light and dark.
 */
export function toggleTheme() {
  document.documentElement.classList.toggle("dark");
  localStorage.setItem(
    "theme",
    document.documentElement.classList.contains("dark") ? "dark" : "light",
  );
  updateThemeIcons();
  initCharts();
}

/**
 * Updates the theme toggle icons based on current theme.
 */
export function updateThemeIcons() {
  const isDark = document.documentElement.classList.contains("dark");
  const sunIcon = document.getElementById("sunIcon");
  const moonIcon = document.getElementById("moonIcon");
  if (sunIcon) sunIcon.classList.toggle("hidden", isDark);
  if (moonIcon) moonIcon.classList.toggle("hidden", !isDark);
}
