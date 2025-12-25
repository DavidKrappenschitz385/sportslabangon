from playwright.sync_api import sync_playwright, expect
import os

def run():
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page()

        # Load the local HTML file
        file_path = f"file://{os.getcwd()}/verification/repro_register.html"
        page.goto(file_path)

        # Verify title
        expect(page.get_by_role("heading", name="Register Team Mockup")).to_be_visible()

        # Verify dropdown presence
        dropdown = page.locator("#existing_team")
        expect(dropdown).to_be_visible()

        # Verify input field is initially empty
        team_name_input = page.locator("#team_name")
        expect(team_name_input).to_be_empty()

        # Select "Team Alpha" and verify autofill
        dropdown.select_option("Team Alpha")
        expect(team_name_input).to_have_value("Team Alpha")

        # Screenshot
        page.screenshot(path="verification/dropdown_test.png")
        print("Verification successful, screenshot saved.")

        browser.close()

if __name__ == "__main__":
    run()
