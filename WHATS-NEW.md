# What's new in the Tire Guide

Plain-language release notes for the people who use the guide. Everything
here is something you can see or feel on the site. The developer record,
with the reasons and the file names, is `CHANGELOG.md`.

How this file works: `RTG_Whats_New` reads it and shows it at
`/tire-guide/whats-new/` and in the guide's "What's new" button. One
`## version - date` heading per release, an optional intro line, then
bullets that open with a bold lead sentence. Releases with nothing an owner
would notice are simply left out. Everything above the first heading is
skipped. Keep it friendly: no file names, no class names, no test counts.

## 2.0.7 - 2026-09-03

The guide can now help you choose, not just browse.

- **"Help me choose".** Tap the new button beside Search, answer three quick questions (your Rivian, what matters most, your budget), and get three picks with a reason and an honest trade-off for each. Every pick fits your vehicle and every number comes from the guide itself.
- **Picks you can read at a glance.** Each pick leads with why it was chosen, shows the price on its own, and lays out efficiency, load index, warranty and owner rating as four tiles, the same way the tire page does. From any pick, "Show in guide" jumps to its card with the right vehicle and size already selected.
- **"What owners say" on tire pages.** Once a tire has a couple of written reviews, its page opens with a short summary of what owners like and what they don't, so you don't have to read forty reviews to get the gist.
- **A plain-words comparison.** The compare page now starts with a paragraph that says where each tire wins and what you give up, using the same numbers as the grid.
- **Load index on every card.** Each card now shows the tire's load index, so you can compare tires on it without opening them.
- **Sharing a tire page shows a proper preview image.** Links to tire pages pasted into texts and social posts now come with the RivianTrackr preview image instead of a blank card.

## 1.92.1 - 2026-09-03

- **The button is called Changelog now.** Same notes, clearer name.

## 1.92.0 - 2026-09-03

- **A "What's new" button in the guide.** It sits in the filter bar and opens these notes, with a dot when there is something you have not seen yet. The notes also live on their own page, so you can send someone the link.

## 1.91.2 - 2026-09-02

- **The tire page photo now lines up with the info beside it.** No more empty space under the buttons.
- **"Fits R2" says just that.** The load index tile already shows the numbers, so the chip stopped repeating them. A tire that misses the minimum keeps the numbers on the chip, since that is the warning.

## 1.91.0 - 2026-09-02

The tire page got a full redesign, so it reads like a product page.

- **The model name is the headline.** The brand sits above it, and the size is a chip next to the "Fits R1" or "Fits R2" pill.
- **One sentence tells the story.** Under the title: what kind of tire it is, which Rivian it fits, the price per tire and per set, and the real-world efficiency owners are seeing.
- **Four quick stats up top.** Efficiency, price, mileage warranty and load index, with "Not listed" when we do not have a number.
- **Specs on the left, related tires on the right.** Other sizes of the same tire, and the best similar tires in the same size, each with its efficiency and fitment chip.
- **Reviews show the month they were written.**
- **A buy bar on phones.** Scroll past the buttons and the price and the "View at" button follow you at the bottom of the screen.

## 1.90.1 - 2026-09-02

- **The compare button is a plus.** Tap it to add a tire to your comparison and it turns into a check. Tap again to take it out.

## 1.90.0 - 2026-09-02

- **Compare and Share moved off the photo.** They sit next to the tire name on every card now, where they are easy to see against a dark tread photo and easier to tap on a phone.
- **Favorites are gone.** Very few people used them, and they took up room on every card. Compare is the better way to shortlist tires.

## 1.89.0 - 2026-09-02

A pass over what each card tells you.

- **Cards say which Rivian a tire fits.** "Fits R1" or "Fits R2" leads the chip row, so the All view makes sense at a glance.
- **The button names the store.** "View at Tire Rack" instead of "View Tire".
- **Prices in whole dollars,** per tire and for a set of four.
- **A heads-up on thin efficiency data.** When fewer than three vehicles or 2,000 miles are behind a number, it is dimmed and marked "Limited data".
- **"Price as of" dates,** so you know how fresh a price is, and a note when one may be out of date.
- **A 3PMS chip** for tires with the severe snow rating, and "Not listed" instead of a blank when we do not know the mileage warranty.

## 1.88.0 - 2026-09-01

The guide started helping you decide, not just browse.

- **A load index warning.** If a tire's load index is below what your Rivian needs (116 for the R1, 112 for the R2), the card, the tire page and the compare page say so.
- **Set-of-four pricing.** Every price shows the cost of four as well, since nobody buys one tire.
- **Tire pages got Compare, Share and "Show more reviews".**
- **Tire pages link to other sizes and similar tires,** so a search that lands you on one tire gives you somewhere to go next.
- **The compare page can add a tire without going back.** Search the catalog right there, remove a single column, and click through to any tire's page.
- **The guide remembers your Rivian.** Pick R1 or R2 once and it is selected the next time you visit.
- **Honest empty results.** "No tires in 275/65R20 yet" when we simply do not have any, instead of suggesting filters to loosen that would not help.

## 1.87.0 - 2026-09-01

- **Price filters above $600 work everywhere.** On some setups a ceiling above $600 was quietly ignored.
- **No more flash of "No tires match" while a page loads.**
- **The compare page's "Browse Tires" link goes to the right page** on every site.

## 1.86.0 - 2026-08-30

- **Faster pages.** The guide's data is cached properly and no longer reloads itself every five minutes.
- **Every store link works on the compare page.** A few retailers showed on the guide but lost their button on the compare page.

## 1.85.0 - 2026-08-30

- **Shared comparison links stay true.** A comparison link used to drift to different tires as the catalog changed. Links now name the tires themselves.
- **The back button works the way it should.** Going back restores exactly the filters that page had.
