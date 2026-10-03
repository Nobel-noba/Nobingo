import asyncio
import io
import os
import sys
import edge_tts
from pydub import AudioSegment

# Force UTF-8 output for Windows console
sys.stdout.reconfigure(encoding="utf-8")

BASE_DIR = "am"

VOICE_PROFILES = {
    "fm": {
        "voice": "am-ET-MekdesNeural",
        "rate": "-20%",
        "pitch": "+0Hz",
    },
    "ma": {
        "voice": "am-ET-AmehaNeural",
        "rate": "-10%",
        "pitch": "-5Hz",
    },
}

# Standard 75-Ball Bingo ranges
BINGO_COLUMNS = [
    ("B", "ቢ", range(1, 16)),
    ("I", "አይ", range(16, 31)),
    ("N", "ኤን", range(31, 46)),
    ("G", "ጂ", range(46, 61)),
    ("O", "ኦ", range(61, 76)),
]

ONES = {
    1: "አንድ",
    2: "ሁለት",
    3: "ሦስት",
    4: "አራት",
    5: "አምስት",
    6: "ስድስት",
    7: "ሰባት",
    8: "ስምንት",
    9: "ዘጠኝ",
}

TENS = {
    10: "አሥር",
    20: "ሃያ",
    30: "ሠላሳ",
    40: "አርባ",
    50: "ኃምሳ",
    60: "ስድሳ",
    70: "ሰባ",
}


def number_to_amharic(n: int) -> str:
    """Converts an integer between 1 and 75 into Amharic words."""
    if n in ONES:
        return ONES[n]
    if n in TENS:
        return TENS[n]
    if 11 <= n <= 19:
        return f"አሥራ {ONES[n - 10]}"

    tens_val = (n // 10) * 10
    ones_val = n % 10
    return f"{TENS[tens_val]} {ONES[ones_val]}"


# Game state announcements: (filename, spoken_text)
GAME_STATUS_CALLS = [
    ("winner.wav", "አሸንፏል"),
    ("not_winner.wav", "አላሸነፈም"),
    ("game_paused.wav", "ጨዋታው ቆሟል"),
    ("game_resumed.wav", "ጨዋታው ቀጥሏል"),
    ("game_over.wav", "ጨዋታው አልቋል"),
    ("game_started.wav", "ጨዋታው ጀምሯል"),

]


async def save_tts_wav(text: str, voice: str, rate: str, pitch: str, output_path: str):
    """Streams audio from edge_tts and writes out a .wav file."""
    communicate = edge_tts.Communicate(text=text, voice=voice, rate=rate, pitch=pitch)
    audio_buffer = bytearray()
    async for chunk in communicate.stream():
        if chunk["type"] == "audio":
            audio_buffer.extend(chunk["data"])

    sound = AudioSegment.from_file(io.BytesIO(audio_buffer), format="mp3")
    sound.export(output_path, format="wav")


async def generate_all_bingo_audio():
    for sub_folder, settings in VOICE_PROFILES.items():
        target_dir = os.path.join(BASE_DIR, sub_folder)
        os.makedirs(target_dir, exist_ok=True)

        voice = settings["voice"]
        rate = settings["rate"]
        pitch = settings["pitch"]

        print(f"\n==========================================")
        print(f"Generating {sub_folder.upper()} voice: {voice}")
        print(f"==========================================")

        # 1. Generate Game Status Prompts
        print("\n--- Generating Game Status Sounds ---")
        for filename, spoken_text in GAME_STATUS_CALLS:
            out_file = os.path.join(target_dir, filename)
            await save_tts_wav(spoken_text, voice, rate, pitch, out_file)
            print(f"Saved: {filename:<18} ({spoken_text})")

        # 2. Generate B1 to O75 Numbers
        print("\n--- Generating B1 to O75 Number Calls ---")
        for letter, spoken_letter, num_range in BINGO_COLUMNS:
            for n in num_range:
                filename = f"{letter}{n}.wav"
                out_file = os.path.join(target_dir, filename)
                spoken_callout = f"{spoken_letter}፣ {number_to_amharic(n)}"

                await save_tts_wav(spoken_callout, voice, rate, pitch, out_file)
                print(f"Saved: {filename:<18} ({spoken_callout})")


if __name__ == "__main__":
    asyncio.run(generate_all_bingo_audio())