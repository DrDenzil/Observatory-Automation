# All-Sky Camera Enhancements (Future)

## Current Status ✅
- **Implemented**: 3-camera viewer (Bayfordbury Night/Day + Hemel)
- **Cameras**: camera1, camera2, camera3
- **Features**: 
  - Live JPEG image auto-refresh (30s)
  - MP4 timelapse playback (if available)
  - Responsive UI with camera selector

---

## Planned Enhancements (Priority Order)

### Phase 2: Date Picker & History Browser
**What**: Add ability to view historical images and timelapses by date

**Scope**:
- Backend: `GET /api/allsky/{camera_id}/dates` — list available dates with images
- Backend: `GET /api/allsky/{camera_id}/images/{date}` — list images for a date
- Frontend: Date picker calendar (left sidebar or modal)
- Frontend: Browse images grid for selected date
- Show "X images captured on 2026-06-17"

**Effort**: ~3-4 hours

**Why**: Observers want to check past conditions, review night skies, debug weather issues

---

### Phase 3: Comparison Mode
**What**: Side-by-side or slider comparison of two dates/times

**Scope**:
- Frontend: Toggle to "compare" mode
- Show two image pickers
- Render with split-screen slider (before/after)
- Useful for weather changes, sky conditions over time

**Effort**: ~2 hours

---

### Phase 4: GIF Preview Generation
**What**: Auto-generate looping GIF from first 50 images of the night

**Scope**:
- Backend: On-demand GIF generation from image sequence
- Backend: Cache GIFs for 7 days
- Frontend: Show tiny preview GIF thumbnail on camera card
- Click to expand full GIF or switch to video

**Effort**: ~3-4 hours (need ffmpeg or Pillow)

---

### Phase 5: Multi-Night Timelapse
**What**: Compile timelapse from multiple nights (weekly, monthly)

**Scope**:
- Backend: Generate weekly/monthly timelapse MP4 from images
- Frontend: Selector for "Last 7 days" or "Last 30 days"
- Shows long-term weather trends, sky changes

**Effort**: ~4-5 hours

---

### Phase 6: S3 Migration (Wishlist) ⭐
**What**: Move all-sky images/timelapse from local filesystem to cloud storage (S3/Minio)

**Why**: 
- Scalability: survive disk failures, add cameras without local storage limits
- Backup: automatic cloud redundancy
- Multi-server: multiple frontend instances can serve from single S3 bucket
- Future-proof: gateway to CDN, archival strategies

**Scope**:
- Backend: Replace local filesystem reads with S3 object reads
- Backend: Use `boto3` (AWS SDK) or similar
- Keep `ALLSKY_BASE` as config, but switch to S3 URIs
- Camera machines: switch from SSH upload to S3 upload (via IAM keys or presigned URLs)
- Frontend: no changes needed (still request via `/api/allsky/...`)

**Effort**: ~5-6 hours (backend refactor + camera upload mechanism repoint)

**Dependencies**:
- AWS S3 account or Minio self-hosted
- `boto3` Python package
- IAM credentials for camera machines

**Not blocking**: Current volume-mount approach works fine for 1-3 cameras. S3 needed if:
- Adding more cameras
- Worried about disk space
- Want redundancy/backup

---

## Technical Notes

### Backend Improvements
- Cache `get_latest_image()` results for 30s (filesystem scans slow)
- Implement image date extraction (from filename or EXIF)
- Add support for image listing endpoints

### Frontend Improvements
- Extract camera selector logic into `<CameraButton>` component
- Add CSS transitions for smooth tab switching
- Mobile-responsive date picker (Flatpickr or native input)

---

## File Structure (When Extended)

```
/www/allsky/
├── camera1/
│   ├── 2026-06-15/
│   │   ├── AllSkyImage008040100.jpg
│   │   ├── AllSkyImage008040101.jpg
│   │   └── ...
│   ├── 2026-06-16/
│   │   └── ...
│   ├── 2026-06-17/
│   │   ├── AllSkyImage008040200.jpg
│   │   └── ...
│   ├── 2026-06-17.mp4         (timelapse)
│   ├── weekly_2026-W24.mp4    (optional: last 7 days)
│   └── monthly_2026-06.mp4    (optional: last 30 days)
├── camera2/
│   └── [same structure]
└── camera3/
    └── [same structure]
```

---

## Dependencies (If Added)

- **GIF generation**: `Pillow` or `ffmpeg-python`
- **Date picker**: Already using React Router, can use `react-calendar` or native `<input type="date">`
- **Image EXIF**: `piexif` (Python) for extracting timestamps

---

## Blocking Items

None — all enhancements are independent and can be added incrementally. No test machine needed.

---

## Docker Deployment Notes

### Current: Volume Mount (Development & Production)

**How it works**:
1. Camera machines SSH into host and upload to `/www/allsky/`
2. Docker container mounts that directory as a volume
3. Backend reads from configurable `ALLSKY_BASE` env var

**Example docker-compose.yml**:
```yaml
services:
  observatory-backend:
    image: observatory:latest
    ports:
      - "8000:8000"
    environment:
      - ALLSKY_BASE=/mnt/allsky
    volumes:
      - /www/allsky:/mnt/allsky  # Host path → Container path
```

**Environment variable**:
- `ALLSKY_BASE` defaults to `/www/allsky` (dev)
- Override for Docker: `ALLSKY_BASE=/mnt/allsky`
- Ready for S3 migration later (just change var to S3 URI)

---

### Future: S3 Storage (Wishlist — Phase 6)

When ready to migrate:
1. Set up S3 bucket (AWS or Minio)
2. Refactor backend to use `boto3` instead of local filesystem
3. Point camera upload to S3 (via IAM keys or presigned URLs)
4. No Docker changes needed (same volume-mount config, just backed by S3)

---

## Suggested Execution Order

1. ✅ **Phase 1 (DONE)**: 3-camera viewer
2. ⏳ **Phase 2**: Date picker (high value, small effort)
3. ⏳ **Phase 3**: Comparison slider (nice-to-have)
4. ⏳ **Phase 4**: GIF previews (polish)
5. ⏳ **Phase 5**: Multi-night timelapse (advanced)

---

## Test Plan (When Built)

- [ ] Date picker loads available dates
- [ ] Clicking a date shows grid of images for that day
- [ ] Timelapse loads correctly for selected date
- [ ] Comparison slider works smoothly
- [ ] Mobile layout responsive (test on <600px)
- [ ] Performance: no jank when scrolling images

